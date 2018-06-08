var anthill_form_loaded = false;

jQuery(document).on('change','#gf-anthill-contact-type',function() {
	var val = jQuery(this).val();
	jQuery('.contact_type').hide(250);
	if (val) {
		jQuery('#anthill_'+val.toLowerCase()).show(250);
	}

});

jQuery(document).on('change','#gf-anthill-customer-id',function() {
	var val = jQuery(this).val();
	jQuery('#anthill_customer_contact').hide(250);
	if (val > 0) {
		jQuery('#anthill_customer_contact').show(250);
	}

});

jQuery(document).ready(function() {
	jQuery('#gf-anthill-contact-type,#gf-anthill-customer-id').change();
	anthill_form_loaded = true;
});
