<?php

// GRAVITY FORMS

add_filter( 'gform_form_settings', 'gform_form_settings_anthill', 100, 2 );
function gform_form_settings_anthill($form_settings, $form) {
	
	$gf_anthill_location = esc_attr( rgar( $form, '_gf_anthill_location' ) );
	$gf_anthill_customer = esc_attr( rgar( $form, '_gf_anthill_customer' ) );
	$gf_anthill_contact_type = esc_attr( rgar( $form, '_gf_anthill_contact_type' ) );
	$gf_anthill_customer_contact = esc_attr( rgar( $form, '_gf_anthill_customer_contact' ) );

	$gf_anthill_enquiry = esc_attr( rgar( $form, '_gf_anthill_enquiry' ) );
	$gf_anthill_issue = esc_attr( rgar( $form, '_gf_anthill_issue' ) );
	$gf_anthill_lead = esc_attr( rgar( $form, '_gf_anthill_lead' ) );
	$gf_anthill_sale = esc_attr( rgar( $form, '_gf_anthill_sale' ) );


	// The meta box content
	$locations = Anthill::GetLocations();
	$customerTypes = Anthill::GetCustomerTypes();
	$contactTypes = Anthill::GetContactTypes();

	$customerContactTypes = Anthill::GetCustomerContactTypes();

	$enquiryTypes = Anthill::GetEnquiryTypes();
	$issueTypes = Anthill::GetIssueTypes();
	$leadTypes = Anthill::GetLeadTypes();
	$saleTypes = Anthill::GetSaleTypes();
	
	
	// Content
	foreach ( $locations as $i => $location ) {
		$locations[$i] = '<option value="'.$location->LocationId.'" '.($location->LocationId==$gf_anthill_location? 'selected' : '').'>'.$location->Label.'</option>';
	}
	$anthill_location = '
		<tr>
            <th>Select Location</th>
			<td>
				<select id="gf-anthill-location-id" name="gf-anthill-location-id" >
					<option value="0">None</option>' .
					implode(' ',$locations) .
				'</select>
			</td>
		</tr>
	';
	
	foreach ( $customerTypes as $i => $customerType ) {
		$customerTypes[$i] = '<option value="'.$customerType->id.'" '.($customerType->id==$gf_anthill_customer? 'selected' : '').'>'.$customerType->name.'</option>';
	}	
	$anthill_customer = '
		<tr>
            <th>Select Customer Type</th>
			<td>
				<select id="gf-anthill-customer-id" name="gf-anthill-customer-id" >
					<option value="0">None</option>' .
					implode(' ',$customerTypes) .
				'</select>
			</td>
		</tr>
	';
		
	foreach ( $customerContactTypes as $i => $customerContactType ) {
		$customerContactTypes[$i] = '<option value="'.$customerContactType->id.'" '.($customerContactType->id==$gf_anthill_customer_contact? 'selected' : '').'>'.$customerContactType->name.'</option>';
	}	
	$anthill_customer_contact = '
		<tr class="hidden" id="anthill_customer_contact">
            <th>Select Customer Contact Type</th>
			<td>
				<select id="gf-anthill-customer-contact-id" name="gf-anthill-customer-contact-id" >
					<option value="0">None</option>' .
					implode(' ',$customerContactTypes) .
				'</select>
			</td>
		</tr>
	';	
	
	foreach ( $contactTypes as $i => $contactType ) {
		$contactTypes[$i] = '<option value="'.$contactType.'" '.($contactType==$gf_anthill_contact_type? 'selected' : '').'>'.$contactType.'</option>';
	}	
	$anthill_contact_type = '
		<tr>
            <th>Select Contact Type</th>
			<td>
				<select id="gf-anthill-contact-type" name="gf-anthill-contact-type" >
					<option value="0">None</option>' .
					implode(' ',$contactTypes) .
				'</select>
			</td>
		</tr>
	';	
	
	foreach ( $enquiryTypes as $i => $enquiryType ) {
		$enquiryTypes[$i] = '<option value="'.$enquiryType->id.'" '.($enquiryType->id==$gf_anthill_enquiry? 'selected' : '').'>'.$enquiryType->name.'</option>';
	}	
	$anthill_enquiry = '
		<tr class="hidden contact_type" id="anthill_enquiry">
            <th>Select Enquiry Type</th>
			<td>
				<select id="gf-anthill-enquiry-id" name="gf-anthill-enquiry-id" >
					<option value="0">None</option>' .
					implode(' ',$enquiryTypes) .
				'</select>
			</td>
		</tr>
	';		
		
	if (!empty($issueTypes)) {
		foreach ( $issueTypes as $i => $issueType ) {
			$issueTypes[$i] = '<option value="'.$issueType->id.'" '.($issueType->id==$gf_anthill_issue? 'selected' : '').'>'.$issueType->name.'</option>';
		}	
	}
	$anthill_issue = '
		<tr class="hidden contact_type" id="anthill_issue">
            <th>Select Issue Type</th>
			<td>
				<select id="gf-anthill-issue-id" name="gf-anthill-issue-id" >
					<option value="0">None</option>' .
					implode(' ',$issueTypes) .
				'</select>
			</td>
		</tr>
	';	

	foreach ( $leadTypes as $i => $leadType ) {
		$leadTypes[$i] = '<option value="'.$leadType->id.'" '.($leadType->id==$gf_anthill_lead? 'selected' : '').'>'.$leadType->name.'</option>';
	}	
	$anthill_lead = '
		<tr class="hidden contact_type" id="anthill_lead">
            <th>Select Lead Type</th>
			<td>
				<select id="gf-anthill-lead-id" name="gf-anthill-lead-id" >
					<option value="0">None</option>' .
					implode(' ',$leadTypes) .
				'</select>
			</td>
		</tr>
	';	
	
	foreach ( $saleTypes as $i => $saleType ) {
		$saleTypes[$i] = '<option value="'.$saleType->id.'" '.($saleType->id==$gf_anthill_sale? 'selected' : '').'>'.$saleType->name.'</option>';
	}	
	$anthill_sale = '
		<tr class="hidden contact_type" id="anthill_sale">
            <th>Select Sale Type</th>
			<td>
				<select id="gf-anthill-sale-id" name="gf-anthill-sale-id" >
					<option value="0">None</option>' .
					implode(' ',$saleTypes) .
				'</select>
			</td>
		</tr>
	';	


		
	$form_settings['Anthill'] = array(
		'anthill_location'			=> $anthill_location,
		'anthill_customer'			=> $anthill_customer,
		'anthill_customer_contact'	=> $anthill_customer_contact,
		'anthill_contact_type'		=> $anthill_contact_type,
		'anthill_customer'			=> $anthill_customer,
		'anthill_enquiry'			=> $anthill_enquiry,
		'anthill_issue'				=> $anthill_issue,
		'anthill_lead'				=> $anthill_lead,
		'anthill_sale'				=> $anthill_sale,
	);
	return $form_settings;
}

add_filter( 'gform_pre_form_settings_save', 'gform_form_settings_anthill_save',10 );
function gform_form_settings_anthill_save($updated_form) {
	$updated_form['_gf_anthill_location']			= rgpost( 'gf-anthill-location-id' );
	$updated_form['_gf_anthill_customer']			= rgpost( 'gf-anthill-customer-id' );
	$updated_form['_gf_anthill_contact_type']		= rgpost( 'gf-anthill-contact-type' );
	$updated_form['_gf_anthill_customer_contact']	= rgpost( 'gf-anthill-customer-contact-id' );
	$updated_form['_gf_anthill_enquiry']			= rgpost( 'gf-anthill-enquiry-id' );
	$updated_form['_gf_anthill_issue']				= rgpost( 'gf-anthill-issue-id' );
	$updated_form['_gf_anthill_lead']				= rgpost( 'gf-anthill-lead-id' );
	$updated_form['_gf_anthill_sale']				= rgpost( 'gf-anthill-sale-id' );
	return $updated_form;
}




