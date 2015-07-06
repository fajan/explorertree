jQuery.fn.explorerTree = function(opts){
	var $ = jQuery;
	$(this).each(function(){
		var tree_opts = $.extend({onselect:null},opts||{}), $tree_root = $(this), tree_selected = ':';
		var setselected = function(e){
			if ($(this).data('itemid') != tree_selected){
				var $elem = $(this),
					is_ns = $elem.is('.explorertree_folder>.li>a'),
				    call = (is_ns && tree_opts.onselectns) ||  (!is_ns && tree_opts.onselectpage);
				$tree_root.find('.explorertree_selected').removeClass('explorertree_selected');
				$elem.addClass('explorertree_selected');
				tree_selected = $elem.data('itemid');
				
				if (call){
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
				
			}
			return true;
		}
		var foldinghandler = function(e){
			if (!$(e.target).is($('>.li',this)) && !$(e.target).is($('>.li>a',this))) return true;
			if ($(this).hasClass('open')){$(this).removeClass('open loading').addClass('closed'); return false;}
			$(this).removeClass('closed').addClass('open');
			if (!$(this).has('>ul.explorertree').length){
				var container = this;
				$(this).addClass('loading');
				$.post(DOKU_BASE + 'lib/exe/ajax.php',
					{call: 'plugin_explorertree',operation:'explorertree_branch',itemid:$(this).data('itemid'),'env':opts.env, sectok: tree_opts.token},
					function(r){
						if (r.token) tree_opts.token = r.token;
						if (r.error) {alert(r.msg); return;}
						$(container).append(r.html).removeClass('loading');
					}
				);
			}
			return false;
		}
		$(this).on('click','.li>a',setselected);
		$(this).on('click','.explorertree_folder',foldinghandler);
	});
	
}

