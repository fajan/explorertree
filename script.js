jQuery.fn.explorerTree = function(opts){
	var $ = jQuery;
	$(this).each(function(){
		var tree_opts = $.extend({onselect:null},opts||{}), $tree_root = $(this), tree_selected = ':', selected_class = tree_opts.classname+"_selecter", dbcl = {id: null, TO: null, clicks: 0};
		var setselected = function(){
			if ($(this).data('itemid') != tree_selected){
				var $elem = $(this),
					is_ns = $elem.is('.folder>.li>a'),
				    ajax_call = (is_ns && tree_opts.onselectns === true) ||  (!is_ns && tree_opts.onselectpage === true),
				    function_call = (is_ns && typeof tree_opts.onselectnsjs === 'function' ? tree_opts.onselectnsjs : undefined) ||  (!is_ns && typeof tree_opts.onselectpagejs === 'function' ? tree_opts.onselectpagejs : undefined) || null;
				$tree_root.find('.'+selected_class).removeClass(selected_class);
				$elem.addClass(selected_class);
				tree_selected = $elem.data('itemid');
				$tree_root.trigger('tree_selected',[$elem.data('itemid'),$elem,$tree_root]);
				if (ajax_call){
					$.post(DOKU_BASE + 'lib/exe/ajax.php',
						{ call:'plugin_explorertree', operation: 'callback', event: is_ns ? 'ns_selected_cb':'page_selected_cb', loader: tree_opts.loader, route: tree_opts.route, sectok: tree_opts.token,itemid:$elem.data('itemid') },
						function(r){
							if (r.token) tree_opts.token = r.token;
							if (r.error) alert(r.msg);
							if (r.func){ 
								try{
									var f = window[r.func];
									if (typeof f === 'function')
										f.apply(null,r.args||[]);
								}catch(e){
									alert(e);
								}
							}else if (r.msg){
								alert(r.msg);
							}
						}
					);
				}
				if (function_call){
					function_call.apply(null,[$elem.data('itemid'),$elem,$tree_root]);
				}
				$tree_root.trigger('tree_selected_'+(is_ns?'ns':'page'),[$elem.data('itemid'),$elem,$tree_root]);
			}
			return true;
		}
		var setselectednodblclick = function(e){
			var id = $(this).data('itemid'), $elem = this;
			if (dbcl.id != id){	
				dbcl.clicks = 0;
				dbcl.id = id;
				dbcl.TO = null;	
			}
			dbcl.clicks++;
			if(dbcl.clicks == 1) {
				dbcl.TO = setTimeout(function(){
					if (dbcl.id == id){
						var c = dbcl.clicks;
						dbcl.clicks = 0;
						if(c == 1){
							setselected.call($elem);	// on singleclick
						}
					}
				},500);
			}
			if (dbcl.clicks == 2){
				clearTimeout(dbcl.TO);
				dbcl.clicks = 0;
			}
		}

		var foldinghandler = function(e){
			if (!$(e.target).is($('>.li',this)) && !$(e.target).is($('>.li>a',this))) return true;
			var $elem = $(this);
			if ($(this).hasClass('open')){
				$(this).removeClass('open loading').addClass('closed'); return false;
				$tree_root.trigger('tree_folder_closed',[$elem.data('itemid'),$elem,$tree_root]);
			}
			$(this).removeClass('closed').addClass('open');
			$tree_root.trigger('tree_folder_open',[$elem.data('itemid'),$elem,$tree_root]);
			if (!$elem.has('>ul.'+tree_opts.classname).length){
				$(this).addClass('loading');
				$.post(DOKU_BASE + 'lib/exe/ajax.php',
					{call: 'plugin_explorertree',operation:'explorertree_branch',itemid:$elem.data('itemid'), loader: tree_opts.loader, route: tree_opts.route, sectok: tree_opts.token},
					function(r){
						if (r.token) tree_opts.token = r.token;
						if (r.error) {alert(r.msg); return;}
						$elem.append(r.html).removeClass('loading');
						$tree_root.trigger('tree_folder_open_ready',[$elem.data('itemid'),$elem,$tree_root]);
					}
				);
			}else{
				$tree_root.trigger('tree_folder_open_ready',[$elem.data('itemid'),$elem,$tree_root]);
			}
			return false;
		}
		$(this).on('click','.li>a',setselectednodblclick);
		$(this).on('dblclick','.folder',foldinghandler);
	});
	
}

