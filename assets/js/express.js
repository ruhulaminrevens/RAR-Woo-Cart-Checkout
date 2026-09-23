(function(){
	'use strict';
	var cfg=window.RAR_WCC_EXPRESS||{};
	if(!cfg.selector)return;

	if(window.__nabiadExpressBuyNowV12&&!window.__RAR_WCC_EXPRESS_LOADED__)return;
	if(window.__RAR_WCC_EXPRESS_LOADED__)return;
	window.__RAR_WCC_EXPRESS_LOADED__=true;
	window.__nabiadExpressBuyNowV12=true;

	document.documentElement.style.setProperty('--rar-wcc-primary',cfg.primaryColor||'#117865');

	var modal=null,checkoutFrame=null,addFrame=null,submitted=false,checkoutStarted=false,checkoutObserver=null,checkoutTimer=null;

	function escapeHTML(value){
		return String(value||'').replace(/[&<>"']/g,function(ch){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];});
	}
	function restoreButton(btn,label){
		if(!btn)return;
		btn.classList.remove('rar-wcc-buy-now-loading');
		btn.disabled=false;
		btn.textContent=label||'Buy now';
	}
	function disconnectObserver(){
		if(checkoutObserver){try{checkoutObserver.disconnect();}catch(e){}checkoutObserver=null;}
	}
	function closeModal(){
		disconnectObserver();
		if(checkoutTimer){window.clearTimeout(checkoutTimer);checkoutTimer=null;}
		if(!modal)return;
		modal.remove();modal=null;checkoutFrame=null;addFrame=null;
		document.body.classList.remove('rar-wcc-express-open');
		if(submitted)window.location.reload();
	}
	function checkoutURL(){
		var path=String(cfg.checkoutPath||'/checkout/');
		if(path.charAt(0)!=='/')path='/'+path;
		return window.location.origin+path+(path.indexOf('?')===-1?'?':'&')+'rar_wcc_express=1&t='+Date.now();
	}
	function startCheckout(){
		if(checkoutStarted||!checkoutFrame)return;
		checkoutStarted=true;
		checkoutFrame.src=checkoutURL();
	}
	function replaceHeadingText(doc,from,to){
		if(!to)return;
		doc.querySelectorAll('h1,h2,h3,h4,.wd-title-text,.element-title,.title').forEach(function(node){
			var txt=(node.textContent||'').trim();
			if(txt.toLowerCase()===String(from).toLowerCase())node.textContent=to;
		});
	}
	function hideMarketingOptIn(doc){
		doc.querySelectorAll('label').forEach(function(label){
			var txt=(label.textContent||'').toLowerCase();
			if(txt.indexOf('marketing email')!==-1||txt.indexOf('marketing emails')!==-1){
				var holder=label.closest('p,.form-row,li,div')||label;
				holder.style.display='none';
			}
		});
	}
	function refineCheckout(doc){
		replaceHeadingText(doc,'Billing Details',cfg.deliveryHeading||'Delivery Information');
		replaceHeadingText(doc,'Billing details',cfg.deliveryHeading||'Delivery Information');
		replaceHeadingText(doc,'Your Order',cfg.orderHeading||'Order Summary');
		replaceHeadingText(doc,'Your order',cfg.orderHeading||'Order Summary');
		doc.querySelectorAll('th,td,span,strong').forEach(function(el){
			if((el.textContent||'').trim()==='Shipment')el.textContent='Shipping';
		});
		var coupon=doc.querySelector('.woocommerce-form-coupon-toggle');
		if(coupon){
			var a=coupon.querySelector('a');
			if(a)a.textContent='Apply coupon';
			Array.prototype.forEach.call(coupon.childNodes,function(n){
				if(n.nodeType===3&&/Have a coupon\?/i.test(n.nodeValue||''))n.nodeValue='Promo code: ';
			});
		}
		hideMarketingOptIn(doc);
	}
	function installCheckoutObserver(doc){
		disconnectObserver();
		var scheduled=false;
		checkoutObserver=new MutationObserver(function(){
			if(scheduled)return;
			scheduled=true;
			window.setTimeout(function(){scheduled=false;refineCheckout(doc);},80);
		});
		checkoutObserver.observe(doc.body,{childList:true,subtree:true});
	}
	function iframeCSS(){
		var primary=String(cfg.primaryColor||'#117865').replace(/[^#a-fA-F0-9]/g,'')||'#117865';
		var hideAdditional=cfg.hideAdditional?'.woocommerce-additional-fields{display:none!important}':'';
		var hideAccount=cfg.hideAccount?'.woocommerce-account-fields,.woocommerce-shipping-fields{display:none!important}':'';
		return '.whb-header,.top-bar,.footer-container,.wd-prefooter,.wd-toolbar,.scrollToTop,.page-title{display:none!important}'+
			'html,body{background:#f5f7f8!important;overflow-x:hidden!important}body{padding:0!important;margin:0!important}'+
			'.main-page-wrapper{padding-top:0!important;margin-top:0!important;min-height:0!important}'+
			'.main-page-wrapper>.container,.main-page-wrapper .container{width:100%!important;max-width:none!important;padding-left:10px!important;padding-right:10px!important}'+
			'.checkout-steps,.wd-checkout-steps,.woocommerce-form-login-toggle{display:none!important}#billing_country_field,#billing_postcode_field{display:none!important}'+hideAdditional+hideAccount+
			'.woocommerce-form-coupon-toggle{margin:8px 0 12px!important;padding:9px 11px!important;background:#fff!important;border:1px solid #e6ece9!important;border-radius:10px!important;font-size:13px!important}'+
			'.woocommerce-checkout .col2-set,.woocommerce-checkout-review-order{float:none!important;width:100%!important;max-width:100%!important}'+
			'.woocommerce-checkout .col-1,.woocommerce-checkout .col-2{width:100%!important;float:none!important}'+
			'.woocommerce-billing-fields,.checkout-order-review,.woocommerce-checkout-review-order{background:#fff!important;border:1px solid #e7ece9!important;border-radius:12px!important;padding:15px!important;margin-bottom:12px!important}'+
			'.woocommerce-checkout .form-row{margin-bottom:9px!important}.woocommerce-checkout label{font-size:13px!important;margin-bottom:4px!important}'+
			'.woocommerce-checkout input.input-text,.woocommerce-checkout textarea,.woocommerce-checkout select,.select2-selection{min-height:40px!important;border-radius:9px!important}'+
			'.woocommerce-checkout textarea{min-height:64px!important}.woocommerce-checkout h3,.woocommerce-checkout h2{font-size:18px!important;margin:0 0 11px!important}'+
			'.shop_table.woocommerce-checkout-review-order-table{margin-bottom:8px!important}.woocommerce-checkout-review-order-table td,.woocommerce-checkout-review-order-table th{padding-top:8px!important;padding-bottom:8px!important}'+
			'.woocommerce-checkout-review-order-table .product-name img{max-width:44px!important;height:auto!important}.woocommerce-checkout-payment{box-shadow:none!important;background:transparent!important;margin-top:6px!important}'+
			'.woocommerce-checkout-payment .wc_payment_method{border:1px solid #e6ece9!important;border-radius:10px!important;padding:10px!important;margin-bottom:8px!important;background:#fff!important}'+
			'.woocommerce-checkout-payment .payment_box{background:#f7faf8!important;border-radius:8px!important;padding:10px!important;margin:8px 0 0!important}'+
			'.woocommerce-privacy-policy-text{font-size:11px!important;line-height:1.4!important;color:#68756f!important}.woocommerce-terms-and-conditions-wrapper{font-size:12px!important}'+
			'#place_order{width:100%!important;min-height:46px!important;border-radius:10px!important;background:'+primary+'!important;border-color:'+primary+'!important;font-weight:700!important;font-size:15px!important}'+
			'@media(max-width:767px){.main-page-wrapper>.container,.main-page-wrapper .container{padding-left:7px!important;padding-right:7px!important}.woocommerce-billing-fields,.checkout-order-review,.woocommerce-checkout-review-order{padding:12px!important}.woocommerce-checkout h3,.woocommerce-checkout h2{font-size:17px!important}}';
	}
	function decorateCheckout(){
		if(!checkoutFrame)return;
		try{
			var win=checkoutFrame.contentWindow;
			var doc=checkoutFrame.contentDocument||win.document;
			if(!doc||!doc.head||!doc.body)return;
			var href=String(win.location.href||'');
			if(href.indexOf('order-received')!==-1){window.location.href=href;return;}
			if(!doc.getElementById('rar-wcc-express-frame-css')){
				var s=doc.createElement('style');s.id='rar-wcc-express-frame-css';s.textContent=iframeCSS();doc.head.appendChild(s);
			}
			refineCheckout(doc);installCheckoutObserver(doc);
			var loader=modal&&modal.querySelector('.rar-wcc-express-loader');if(loader)loader.style.display='none';
		}catch(err){
			var loader2=modal&&modal.querySelector('.rar-wcc-express-loader');
			if(loader2)loader2.innerHTML='<span class="rar-wcc-express-spinner"></span><span>'+escapeHTML(cfg.loaderText||'Opening checkout…')+'</span>';
		}
	}
	function openModal(){
		if(modal)return;
		modal=document.createElement('div');
		modal.className='rar-wcc-express-overlay';
		modal.innerHTML='<div class="rar-wcc-express-dialog" role="dialog" aria-modal="true" aria-label="'+escapeHTML(cfg.title||'Express Checkout')+'">'+
			'<div class="rar-wcc-express-head"><div class="rar-wcc-express-title-wrap"><div class="rar-wcc-express-shield">✓</div>'+
			'<div><div class="rar-wcc-express-title">'+escapeHTML(cfg.title||'Express Checkout')+'</div><div class="rar-wcc-express-subtitle">'+escapeHTML(cfg.subtitle||'Fast & secure checkout')+'</div></div></div>'+
			'<button type="button" class="rar-wcc-express-close" aria-label="Close checkout">&times;</button></div>'+
			'<div class="rar-wcc-express-frame-wrap"><div class="rar-wcc-express-loader"><span class="rar-wcc-express-spinner"></span><span>'+escapeHTML(cfg.loaderText||'Preparing secure checkout…')+'</span></div>'+
			'<iframe class="rar-wcc-express-add-frame" name="rarWCCExpressAddFrame" title="Adding product"></iframe><iframe class="rar-wcc-express-frame" title="'+escapeHTML(cfg.title||'Express Checkout')+'"></iframe></div>'+
			'<div class="rar-wcc-express-foot">'+escapeHTML(cfg.footerText||'')+' · <a href="'+escapeHTML(cfg.checkoutPath||'/checkout/')+'" target="_top">'+escapeHTML(cfg.openFullText||'Open full checkout')+'</a></div></div>';
		document.body.appendChild(modal);document.body.classList.add('rar-wcc-express-open');
		addFrame=modal.querySelector('.rar-wcc-express-add-frame');
		checkoutFrame=modal.querySelector('.rar-wcc-express-frame');
		addFrame.addEventListener('load',function(){if(submitted)window.setTimeout(startCheckout,120);});
		checkoutFrame.addEventListener('load',decorateCheckout);
		modal.querySelector('.rar-wcc-express-close').addEventListener('click',closeModal);
		modal.addEventListener('click',function(e){if(e.target===modal)closeModal();});
	}
	function nativeFallback(btn){
		try{
			btn.dataset.rarWccExpressBypass='1';btn.click();
			window.setTimeout(function(){delete btn.dataset.rarWccExpressBypass;},1200);
		}catch(e){window.location.href=cfg.checkoutPath||'/checkout/';}
	}
	document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal)closeModal();});
	document.addEventListener('click',function(e){
		var btn=e.target.closest&&e.target.closest(cfg.selector);
		if(!btn||btn.dataset.rarWccExpressBypass==='1')return;
		var form=btn.closest('form.cart');if(!form)return;
		var variation=form.querySelector('input[name="variation_id"]');
		var standardAdd=form.querySelector('.single_add_to_cart_button');
		if((variation&&(!variation.value||variation.value==='0'))||(standardAdd&&(standardAdd.disabled||standardAdd.classList.contains('disabled'))))return;
		var productType=document.body.className||'';
		if(productType.indexOf('product-type-external')!==-1||productType.indexOf('product-type-grouped')!==-1)return;

		e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();
		var originalText=btn.textContent;
		btn.classList.add('rar-wcc-buy-now-loading');btn.disabled=true;btn.textContent=cfg.loadingText||'Preparing…';
		try{
			openModal();
			var oldTarget=form.getAttribute('target');
			var hidden=document.createElement('input');
			hidden.type='hidden';hidden.name=btn.name||'wd-add-to-cart';hidden.value=btn.value||'';
			hidden.setAttribute('data-rar-wcc-temp','1');form.appendChild(hidden);
			form.setAttribute('target','rarWCCExpressAddFrame');submitted=true;checkoutStarted=false;
			HTMLFormElement.prototype.submit.call(form);
			checkoutTimer=window.setTimeout(startCheckout,4500);
			window.setTimeout(function(){
				if(oldTarget===null)form.removeAttribute('target');else form.setAttribute('target',oldTarget);
				if(hidden.parentNode)hidden.parentNode.removeChild(hidden);
				restoreButton(btn,originalText);
			},700);
		}catch(err){
			closeModal();submitted=false;restoreButton(btn,originalText);nativeFallback(btn);
		}
	},true);
})();
