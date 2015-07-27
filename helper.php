<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_explorertree extends DokuWiki_Plugin {

	private $routes = array();		// array of routes (registered classes)
	private $memcache = false;		// memcache
	
	private $options = array(
		'callbacks' =>array(
			'page_selected_cb' => null,
			'ns_selected_cb' => null,
			'page_selected_js' => null,
			'ns_selected_js' => null,
		),
		'vars'=>array(
			'id'=> null,
			'class' => 'explorertree',
		),
		'init_plugin' => null,
	);

	function cache(){
		if ($this->memcache === false){
			// we will use memcache even it's emulated, as we building a tree requires to search filesystem (multiple fs I/O) and emulated cache only graps one compressed wile from disk (one optimized fs I/O)
			$this->memcache = plugin_load('helper','memcache');
		}
		return $this->memcahce;
	}
	
	
    function getMethods() {
        $result = array();
		$result[] = array(
                'name'   => 'registerRoute',
                'desc'   => 'registers a route: the tree will be created by the options and ajax will be routed to the caller class.',
				'parameters' => array(
					'name' => "string unique name, usually the registerer plugin's name (with suffix, if the plugin uses more trees).",
					'options' => "array of options, that replace the original options (see getOptions)",
					)
                );
		$result[] = array(
				'name' => 'getOptions',
				'desc' => 'returns a registered route options or the default options',
				'parameters' => array(
					'name' => "string unique name for the registered options. if omitted, the default options are returned."
					),
				'return' => array('options'=>'array of options or null if the name does not exists.'),
				);
        $result[] = array(
                'name'   => 'getTree',
                'desc'   => 'gets the tree of NS and pages ',
				'parameters' => array(
					'folder' => 'string an already converted filesystem folder of the current namespace',
					),
				'return' => array('tree'=>'array'),
                );
        $result[] = array(
                'name'   => 'htmlExplorer',
                'desc'   => 'gets html explorer of the whole wiki.',
				'parameters' => array(
					'name' => 'string unique name of a callback/data store.',
					'base' => 'string ID of the root node',
					),
				'return' => array('tree'=>'array'),
                );
        return $result;
    }

	function registerRoute($name,array $options){
		$this->routes[$name] = array_replace_recursive ($this->options,$options);
	}
	function getOptions($name = null){
		if (!$name) return $this->options;
		return @$this->routes[$name][$options];
	}

	function loadRoute($name,array $reg = null){
		if (!$name) return $this->options;
		if ((! @$this->routes[$name]) && $reg){
			if (($p = plugin_load($reg['type'],$reg['plugin'])) && $met = $reg['method']){
				call_user_func(array($p,$met),array());
			}
		}
		return @$this->routes[$name];
	}
	

    /**
     * get a combined list of media and page files
     *
     * @param string $folder an already converted filesystem folder of the current namespace
     */
    function getTree($folder=':'){
        global $conf;
		global $ID;
        // read tree structure from pages and media
		$ofolder = $folder;
		if ($folder == '*' || $folder == '') $folder = ':';
		if ($folder[0] != ':') $folder = resolve_id($folder,$ID);
		$dir = strtr(cleanID($folder),':','/');
		if (!($this->cache() && is_array($data = $this->cache()->get('explorertree_cache_'.$dir)))){
			$data = array();
			search($data,$conf['datadir'],'search_index',array('ns'=>getNS($ID)),$dir,$dir == '' ? 1 : count(explode('/',$dir))+1);
			$count = count($data);
			if($count>0) for($i=1; $i<$count; $i++){
				if($data[$i-1]['id'] == $data[$i]['id'] && $data[$i-1]['type'] == $data[$i]['type']) {
					unset($data[$i]);
					$i++;  // duplicate found, next $i can't be a duplicate, so skip forward one
				}
			}
			if ($this->cache()) {
				$this->cache()->set($cache_id = 'explorertree_cache_'.$dir,$data,60);	// store the data itself (cache for one minute)
			}
		}
        return $data;
    }

    /**
     * Display a tree menu to select a page or namespace
     *
     */
    function htmlExplorer($name,$base = ''){
        global $lang;
		if ($base == '' || $base == '*') $base = ':';
        if (!($o = $this->loadRoute($name))){
			return "<div>Invalid explorertree route!</div>";	//TODO: replace with lang...
		}
        $data = $this->getTree($base);
        // wrap a list with the root level around the other namespaces
        if ($base == ':'){
			array_unshift($data, array( 'level' => 0, 'id' => ':', 'type' => 'd',
                   'open' =>'true', 'label' => '['.$lang['mediaroot'].']'));
		}
        $list = html_buildlist($data,
							$class = $o['vars']['class'],
							array($this,'_html_list_tree'),
							array($this,'_html_li_tree')
							);
		if (strncasecmp(trim($list),'<ul ',4)){
			$list = "<ul class='{$class}' >".$list."</ul>";
		}
		if (!($id = $o['vars']['id'])){
			$id = "explorertree_{$name}";
		}
        if ($base == ':'){
			return "<div class='{$class}_root' id='{$id}'>".$list."</div>"
			."<script type='text/javascript'>jQuery(document).ready(function(){jQuery('#{$id}').explorerTree(".$this->_treeOpts($name).")});</script>";
		}
		return $list;

    }
	
	function _treeOpts($name){
		$opts = $this->loadRoute($name);
		$o = array(
			'route' => $name,
			'classname' => $opts['vars']['class'],
			'loader' => $opts['init_plugin'],
			'onselectpage' => (bool)$opts['callbacks']['page_selected_cb'],
			'onselectns' => (bool)$opts['callbacks']['ns_selected_cb'],
			'onselectnsjs' => null,
			'onselectpagejs' => null,
			'token' => getSecurityToken(),
		);
		$json = json_encode($o);
		$json = preg_replace_callback('~("onselect(ns|page)js"\s*:\s*)null\s*,~',function($m) use ($opts){
			if (is_string($x = $opts['callbacks'][$m[2].'_selected_js']) && strlen($x) > 0){
				return $m[1].$x.'||null,';
			}
			return $m[0];
		},$json);
		return $json;
		
	}
	

	
    /**
     * Item formatter for the tree view
     *
     * User function for html_buildlist()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _html_list_tree($item){
        $ret = '';
        // what to display
        if(!empty($item['label'])){
            $base = $item['label'];
        }else{
            $base = ':'.$item['id'];
            $base = substr($base,strrpos($base,':')+1);
        }

        // highlight?
        if( ($item['type']== $this->current_item['type'] && $item['id'] == $this->current_item['id'])) {
            $cl = ' cur';
        } else {
            $cl = '';
        }

        // namespace or page?
        if($item['type']=='d'){
            $ret .= '<a href="#" class="idx_dir'.$cl.' " data-itemid="'.$item["id"].'">';
            $ret .= $base;
            $ret .= '</a>';
        }else{
            $ret .= '<a href="#" class="wikilink1'.$cl.' " data-itemid="'.$item["id"].'">';
            $ret .= noNS($item['id']);
            $ret .= '</a>';
        }
        return $ret;
    }


    function _html_li_tree($item){
        return '<li class="level' . $item['level'] . ' ' .($item["type"] == 'd' ? 'folder ':' ').
               ($item['open'] ? 'open' : 'closed') . '" data-itemid="'.$item["id"].'">';
    }

	
}
// vim:ts=4:sw=4:et: 
