(function($){
 'use strict';
 const form=$('form.auracast-checkout-form');
 if(!form.length)return;
 const money=value=>(window.auracastCheckout?.currency||'৳')+Number(value).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
 const savedFields=['billing_email','billing_country','billing_first_name','billing_last_name','billing_address_1','billing_address_2','billing_city','billing_postcode','billing_phone'];
 function refreshChoices(){
  form.find('.checkout-choice').each(function(){$(this).toggleClass('is-selected',!!$(this).find('input:checked').length);});
  const subtotal=Number(form.find('.woocommerce-checkout-review-order-table').attr('data-subtotal')||0);
  form.find('[data-tip-percent]').each(function(){$(this).text(money(subtotal*Number(this.dataset.tipPercent)/100));});
  form.find('.form-row input.input-text').each(function(){$(this).closest('.form-row').toggleClass('checkout-has-value',!!this.value);});
 }
 form.on('input change','.form-row input.input-text',function(){$(this).closest('.form-row').toggleClass('checkout-has-value',!!this.value);});
 form.on('click','.checkout-summary-toggle',function(){const expanded=this.getAttribute('aria-expanded')!=='true';this.setAttribute('aria-expanded',String(expanded));form.find('.checkout-summary').toggleClass('is-open',expanded);});
 form.on('click','.checkout-add-discount,.checkout-mobile-summary-heading',function(){const expanded=this.classList.contains('checkout-add-discount');form.find('.checkout-mobile-summary-expanded').prop('hidden',!expanded);form.find('.checkout-add-discount').prop('hidden',expanded).attr('aria-expanded',String(expanded));form.find('.checkout-final-total').toggle(!expanded);if(expanded)form.find('#checkout-mobile-discount-code').trigger('focus');});
 form.on('click','.checkout-field-help',function(){const popup=$(this).next('.checkout-help-popup');popup.prop('hidden',!popup.prop('hidden'));});
 const policyDialog=document.getElementById('checkout-policy-dialog');
 form.on('click','[data-checkout-policy]',async function(event){
  if(!policyDialog?.showModal)return;
  event.preventDefault();const link=this,body=policyDialog.querySelector('.checkout-policy-content');policyDialog.querySelector('h2').textContent=link.dataset.checkoutPolicy;body.textContent='Loading…';policyDialog.showModal();
  try{const response=await fetch(link.href,{credentials:'same-origin'});if(!response.ok)throw new Error('Policy unavailable');const doc=new DOMParser().parseFromString(await response.text(),'text/html');const content=doc.querySelector('.entry-content,.policy-body,.reference-policy-body')||doc.querySelector('main');if(!content)throw new Error('Policy unavailable');const clone=content.cloneNode(true);clone.querySelectorAll('h1,.page-heading,.policy-heading').forEach(element=>element.remove());body.replaceChildren(...clone.childNodes);}catch(error){body.textContent='This policy is available on the store website. ';const fallback=document.createElement('a');fallback.href=link.href;fallback.target='_blank';fallback.rel='noopener';fallback.textContent='Open '+link.dataset.checkoutPolicy;body.append(fallback);}
 });
 if(policyDialog){policyDialog.querySelector('[data-checkout-policy-close]').addEventListener('click',()=>policyDialog.close());policyDialog.addEventListener('click',event=>{if(event.target===policyDialog){const rect=policyDialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)policyDialog.close();}});}
 form.on('change','[name=auracast_different_billing]',function(){const different=form.find('[name=auracast_different_billing]:checked').val()==='1';form.find('.checkout-different-billing').prop('hidden',!different);refreshChoices();});
 form.on('change','[name=auracast_tip_choice]',function(){$(document.body).trigger('update_checkout');});
 form.on('change','#checkout-tip-enabled',function(){form.find('.checkout-tip-content').prop('hidden',!this.checked);if(!this.checked){form.find('[name=auracast_tip_choice][value=none]').prop('checked',true);$(document.body).trigger('update_checkout');}});
 form.on('click','.checkout-add-tip',function(){const field=form.find('[name=auracast_tip_custom]');const amount=Math.min(100000,Math.max(0,Number(field.val())||0));field.val(amount||'');form.find('[name=auracast_tip_choice][value='+(amount>0?'custom':'none')+']').prop('checked',true);$(document.body).trigger('update_checkout');});
 form.on('click','[data-tip-step]',function(){const field=form.find('[name=auracast_tip_custom]');field.val(Math.min(100000,Math.max(0,(Number(field.val())||0)+Number(this.dataset.tipStep))));});
 form.on('click','.checkout-apply-coupon',function(){
  const button=$(this),discount=button.closest('.checkout-discount'),code=discount.find('.checkout-discount-code').val().trim(),status=discount.find('.checkout-coupon-status');
  if(!code){status.text('Enter a discount code.');return;}
  button.prop('disabled',true);status.empty();
  $.ajax({type:'POST',url:wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%','apply_coupon'),data:{security:wc_checkout_params.apply_coupon_nonce,coupon_code:code},dataType:'html'}).done(function(response){status.html(response);if(!status.find('.woocommerce-error').length){$(document.body).trigger('applied_coupon_in_checkout',[code]);$(document.body).trigger('update_checkout');}}).fail(function(){status.text('Unable to apply the code. Please try again.');}).always(function(){button.prop('disabled',false);});
 });
 form.on('keydown','.checkout-discount-code',function(event){if(event.key==='Enter'){event.preventDefault();$(this).closest('.checkout-discount').find('.checkout-apply-coupon').trigger('click');}});
 function saveDelivery(){
  try{if(!form.find('#auracast-save-delivery').prop('checked')){localStorage.removeItem(auracastCheckout.savedKey);return;}
   const data={};savedFields.forEach(key=>{const input=document.getElementById(key);if(input)data[key]=input.value;});localStorage.setItem(auracastCheckout.savedKey,JSON.stringify(data));
  }catch(error){/* Browser storage is optional; order submission remains native. */}
 }
 form.on('change','#auracast-save-delivery',saveDelivery);
 form.on('change','input[id^=billing_],select[id^=billing_]',function(){if(form.find('#auracast-save-delivery').prop('checked'))saveDelivery();});
 form.on('checkout_place_order',saveDelivery);
 try{const saved=JSON.parse(localStorage.getItem(auracastCheckout.savedKey)||'null');if(saved){savedFields.forEach(key=>{const input=document.getElementById(key);if(input&&!input.value&&typeof saved[key]==='string')input.value=saved[key];});form.find('#auracast-save-delivery').prop('checked',true);$(document.body).trigger('update_checkout');}}catch(error){/* Ignore unavailable or expired browser storage. */}
 $(document.body).on('updated_checkout',refreshChoices);
 refreshChoices();
})(jQuery);
