(function($){
	'use strict';
	if (window.__RAR_WCC_ADDRESS_LOADED__) return;
	window.__RAR_WCC_ADDRESS_LOADED__ = true;

	var cfg = window.RAR_WCC_ADDRESS || {};
	var cityMap = cfg.cityMap || {};

	var aliases = {
		chittagong:'Chattogram',chattogram:'Chattogram',
		comilla:'Cumilla',cumilla:'Cumilla',
		barisal:'Barishal',barishal:'Barishal',
		bogra:'Bogura',bogura:'Bogura',
		jessore:'Jashore',jashore:'Jashore',
		jhalokathi:'Jhalokati',jhalokati:'Jhalokati',
		chapainawabganj:'Chapainababganj',chapainababganj:'Chapainababganj',
		khagrachari:'Khagrachhari',khagrachhari:'Khagrachhari',
		coxsbazar:"Cox's Bazar"
	};

	function norm(value){return String(value||'').toLowerCase().replace(/&/g,'and').replace(/[^a-z0-9]/g,'');}

	function resolveDistrict(name){
		var k=norm(name);
		if(aliases[k]&&cityMap[aliases[k]]) return aliases[k];
		var match='';
		$.each(cityMap,function(district){
			if(norm(district)===k){match=district;return false;}
		});
		return match;
	}

	function selectedDistrict(stateSelector){
		var $state=$(stateSelector);
		if(!$state.length)return '';
		return $state.is('select')?$.trim($state.find('option:selected').text()):$.trim($state.val());
	}

	function ensureCountry(countrySelector){
		var $country=$(countrySelector);
		if(!$country.length)return;
		if($country.is('select')&&!$country.find('option[value="BD"]').length){
			$country.append($('<option/>',{value:'BD',text:'Bangladesh'}));
		}
		$country.val('BD');
	}

	function ensureCitySelect(citySelector,fieldName){
		var $old=$(citySelector);
		if(!$old.length)return $();
		if($old.is('select'))return $old;

		var current=$.trim($old.val()||'');
		var classes=$old.attr('class')||'';
		var $select=$('<select/>',{
			id:citySelector.replace('#',''),
			name:fieldName,
			class:classes+' rar-wcc-city-select',
			autocomplete:'address-level2',
			'aria-required':'true'
		});
		if($old.prop('required'))$select.prop('required',true);
		$old.replaceWith($select);
		if(current)$select.data('previous-city',current);
		return $select;
	}

	function initSearch($select){
		if(!$select.length||!cfg.searchableCity)return;
		try{
			if($select.hasClass('select2-hidden-accessible')&&$.fn.selectWoo){$select.selectWoo('destroy');}
			else if($select.hasClass('select2-hidden-accessible')&&$.fn.select2){$select.select2('destroy');}
		}catch(e){}
		var options={width:'100%',placeholder:cfg.cityPlaceholder||'Search town / city…',allowClear:false};
		if($.fn.selectWoo)$select.selectWoo(options);
		else if($.fn.select2)$select.select2(options);
	}

	function populate(ctx,keepCurrent){
		if(!cfg.searchableCity)return;
		var $select=ensureCitySelect(ctx.city,ctx.cityName);
		if(!$select.length)return;
		var previous=keepCurrent?($select.val()||$select.data('previous-city')||''):'';
		var district=resolveDistrict(selectedDistrict(ctx.state));
		var list=district&&cityMap[district]?cityMap[district].slice():[];
		if(!list.length){
			$.each(cityMap,function(_,cities){list=list.concat(cities);});
			list=Array.from(new Set(list)).sort();
		}
		$select.empty().append($('<option/>',{value:'',text:'Select town / city'}));
		$.each(list,function(_,city){$select.append($('<option/>',{value:city,text:city}));});
		if(previous&&list.indexOf(previous)!==-1)$select.val(previous);else $select.val('');
		$select.removeData('previous-city');
		initSearch($select);
	}

	function setText(selector,text){
		if(!text)return;
		var $el=$(selector).first();
		if($el.length)$el.text(text);
	}

	function refineCheckout(){
		if(!cfg.checkoutEnabled)return;
		setText('.woocommerce-billing-fields h3',cfg.billingHeading);
		setText('.woocommerce-additional-fields h3',cfg.additionalHeading);
		if(cfg.orderNotesLabel){
			var $label=$('label[for="order_comments"]');
			if($label.length){
				var optional=$label.find('.optional').detach();
				$label.contents().filter(function(){return this.nodeType===3;}).remove();
				$label.prepend(document.createTextNode(cfg.orderNotesLabel+' '));
				if(optional.length)$label.append(optional);
			}
		}
	}

	var checkoutCtx={state:'#billing_state',city:'#billing_city',cityName:'billing_city',country:'#billing_country'};
	var cartCtx={state:'#calc_shipping_state',city:'#calc_shipping_city',cityName:'calc_shipping_city',country:'#calc_shipping_country'};

	function bootCheckout(keepCurrent){
		if(!cfg.checkoutEnabled||!$(checkoutCtx.state).length)return;
		if(cfg.hideCountry)ensureCountry(checkoutCtx.country);
		populate(checkoutCtx,keepCurrent);
		refineCheckout();
	}
	function bootCart(keepCurrent){
		if(!cfg.cartEnabled||!$(cartCtx.state).length)return;
		if(cfg.hideCountry)ensureCountry(cartCtx.country);
		populate(cartCtx,keepCurrent);
	}

	$(document.body).on('change','#billing_state',function(){
		window.setTimeout(function(){
			populate(checkoutCtx,false);
			$(document.body).trigger('update_checkout');
		},80);
	});
	$(document.body).on('change','#calc_shipping_state',function(){
		window.setTimeout(function(){populate(cartCtx,false);},60);
	});
	$(document.body).on('updated_checkout',function(){
		window.setTimeout(function(){bootCheckout(true);},40);
	});
	$(document.body).on('updated_wc_div updated_cart_totals',function(){
		window.setTimeout(function(){bootCart(true);},60);
	});
	$(function(){bootCheckout(true);bootCart(true);refineCheckout();});
})(jQuery);
