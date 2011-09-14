<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Authorize_Net
{		
	/**
	 * The use who wil make the call to the paypal gateway
	*/
	private static $_api_user;

	/**
	 * The password for API user
	*/
	private static $_pwd;	

	/**
	 * The version of the API to use
	*/	
	private static $_api_version;

	/**
	 * The API signature to use
	*/	
	private static $_api_signature;

	/**
	 * A description to use for the transaction
	*/
	private static $_transaction_description;

	/**
	 * The API method currently being utilized
	*/
	private static $_api_method;		

	/**
	 * The API method currently being utilized
	*/
	protected $_api_endpoint;	

	/**
	 * An array for storing all settings
	*/	
	private static $_settings = array();

	/**
	 * An array for storing all request data
	*/	
	private static $_request = array();	

	/**
	 * The final string to be sent in the http query
	*/	
	protected $_http_query;	
	
	/**
	 * The default params for this api
	*/	
	private static	$_default_params;
	
	/**
	 * Constructor method
	*/		
	public static function __construct($payments)
	{
		self::$payments = $payments;				
		self::$_default_params = \Config::get('method_params');
		self::$_api_endpoint = \Config::get('api_endpoint'.'_'.self::$payments::$mode);
		self::$_delimiter = \Config::get('delimiter');		
		self::$_api_settings = array(
			'login'			=> \Config::get('api_username'),
			'tran_key'		=> \Config::get('api_password'),
			'xml_version'	=> '1.0',
			'encoding'		=> 'utf-8',
			'xml_schema'	=> 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"',
			'email_customer'=> \Config::get('email_customer'),
			'test_mode'		=> \Config::get('test_mode')
		);
	}

	/**
	 * Builds a request
	 * @param	array	array of params
	 * @param	string	the api call to use
	 * @param	string	the type of transaction
	 * @return	array	Array of transaction settings
	*/	
	protected function _build_request($params, $transaction_type = NULL)
	{
		$nodes = array();
		$nodes['merchantAuthentication'] = array(
			'name' => self::$_api_settings['login'],
			'transactionKey' =>	self::$_api_settings['tran_key'],		
		);	
		
		if(self::$_api_method == 'createTransactionRequest')
		{		
			$nodes['transactionRequest'] = self::s_transaction_fields($transaction_type, $params);						
			$nodes['transactionRequest']['transactionSettings'] = self::$_transaction_settings();		
		}

		if(self::$_api_method == 'getTransactionDetailsRequest')
		{
			$nodes['transId'] = $params['identifier'];
		}

		if(self::$_api_method == 'ARBGetSubscriptionStatusRequest' OR self::$_api_method == 'ARBUpdateSubscriptionRequest' OR self::$_api_method == 'ARBCancelSubscriptionRequest')
		{
			$nodes['subscriptionId'] = $params['identifier'];
		}	
		
		if(self::$_api_method == 'ARBCreateSubscriptionRequest' OR self::$_api_method == 'ARBUpdateSubscriptionRequest')
		{
			$nodes['subscription'] = self::_transaction_fields($transaction_type, $params);
		}			
								
		$request = self::$payments::build_xml_request(
			self::$_api_settings['xml_version'],
			self::$_api_settings['encoding'],
			$nodes,					
			self::$_api_method,
			self::$_api_settings['xml_schema']
		);
		
		//var_dump($request);exit;
		
		return $request;	
	}
	
	/**
	 * Sets transaction settings
	 * @return	array	Array of transaction settings
	*/		
	protected function _transaction_settings()
	{
		return array(
			'repeated_key' => array(
				'name' => 'setting',
				'wraps'	=> FALSE,
				'values' => array(
					array(
						'settingName' => 'allowPartialAuth', 
						'settingValue'=> TRUE,
					),
					array(
						'settingName' => 'emailCustomer',
						'settingValue' => self::$_api_settings['email_customer']
					),
					array(
						'settingName' => 'recurringBilling',
						'settingValue' => FALSE
					),
					array(
						'settingName' => 'testRequest',
						'settingValue' => self::$_api_settings['test_mode']
					)
				)
			)
		);	
	}

	/**
	 * Sets fields to a request
	 * @param	string	The transaction type
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _transaction_fields($transaction_type, $params)
	{
		$fields = array();
		
		if(!is_null($transaction_type))
		{
			$fields['transactionType'] = $transaction_type;
		}
		
		if(self::$_api_method == 'ARBCreateSubscriptionRequest')
		{

			$fields['name'] = $params['first_name'] . ' ' . $params['last_name'];

			if($params['billing_period'] != 'Month' && $params['billing_period'] != 'Day') 
			{
				return self::return_response(
					'Failure', 
					'invalid_date_params',
					'local_response'
				);
			}
			
			if($params['billing_period'] == 'Month')
			{
				$params['billing_period'] = 'months';
			}
			
			if($params['billing_period'] == 'Day')
			{
				$params['billing_period'] = 'days';
			}
					
			$fields['paymentSchedule'] = array(
				'interval' => array(
					'length' => $params['billing_frequency'],
					'unit' => $params['billing_period'],
				),
				'startDate' => $params['profile_start_date'],
				'totalOccurrences' => $params['total_billing_cycles']
			);
			
			if(isset($params['trial_billing_cycles']) AND isset($params['trial_amt']))
			{
				$fields['paymentSchedule']['interval']['trialOccurrences'] = $params['trial_billing_cycles'];
				$fields['trialAmount'] = $params['trial_amt'];
			}		
		}
		
		if(isset($params['amt']))
		{
			$fields['amount'] = $params['amt'];
		}
		
		if(isset($params['cc_number']))
		{

			$fields['payment']['creditCard'] = self::_add_payment('credit_card', $params);
		}
		
		if(isset($params['identifier']) AND self::$_api_method != 'ARBUpdateSubscriptionRequest')
		{
			$fields['refTransId'] = $params['identifier'];
		}
		
		$fields['order'] = self::_build_order_fields($params);
		
		if(isset($params['tax_amt']))
		{
			$fields['tax'] = array(
				'amount' => $params['tax_amt']
			);
		}		
		
		if(isset($params['duty_amt']))
		{
			$fields['duty'] = array(
				'amount' => $params['duty_amt']
			);	
		}

		if(isset($params['shipping']))
		{
			$fields['shipping'] = array(
				'amount' => $params['shipping_amt']
			);			
		}
		
		if(isset($params['tax_exempt']))
		{
			$fields['taxExempt'] = array(
				'taxExempt' => $params['tax_exempt']
			);			
		}

		if(isset($params['po_num']))
		{
			$fields['poNumber'] = array(
				'poNumber' => $params['po_num']
			);		
		}
		
		$fields['customer'] = self::_build_customer_fields($params);
		
		$fields['billTo'] = self::_build_bill_to_fields($params);

		$fields['shipTo'] = self::_build_ship_to_fields($params);	

		if(isset($params['ip_address']))
		{
			$fields['customerIP'] = $params['ip_address'];
		}	
		
		return $fields;
	}

	/**
	 * Builds fields for order node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/	
	protected function _build_order_fields($params)
	{
		$order = array();
		
		if(isset($params['inv_num']))
		{
			if(isset($params['desc']))
			{
				$order = array(
					'invoiceNumber' => $params['inv_num'],
					'description' => $params['desc']
				);				
			}
			else
			{
				$order = array(
					'invoiceNumber' => $params['inv_num']				
				);
			}
		}
		
		return $order;	
	}

	/**
	 * Builds fields for billTo node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/			
	protected function _build_bill_to_fields($params)
	{
		$bill_to = array();
		
		if(isset($params['first_name']))
		{
			$bill_to['firstName'] = $params['first_name'];
		}	
		
		if(isset($params['last_name']))
		{
			$bill_to['lastName'] = $params['last_name'];
		}

		if(isset($params['business_name']))
		{
			$bill_to['company'] = $params['business_name'];
		}

		if(isset($params['street']))
		{
			$bill_to['address'] = $params['street'];
		}

		if(isset($params['city']))
		{
			$bill_to['city'] = $params['city'];
		}

		if(isset($params['state']))
		{
			$bill_to['state'] = $params['state'];
		}

		if(isset($params['postal_code']))
		{
			$bill_to['zip'] = $params['postal_code'];
		}

		if(isset($params['country']))
		{
			$bill_to['country'] = $params['country'];
		}

		if(isset($params['phone']))
		{
			$bill_to['phoneNumber'] = $params['phone'];
		}	

		if(isset($params['fax']))
		{
			$bill_to['faxNumber'] = $params['fax'];
		}
		
		return $bill_to;		
	}

	/**
	 * Builds fields for customer node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _build_customer_fields($params)
	{
		$customer = array();
		
		if(isset($params['email']))
		{	
			$customer['email'] = $params['email'];
		}

		if(isset($params['phone']))
		{	
			$customer['phoneNumber'] = $params['phone'];
		}

		if(isset($params['fax']))
		{	
			$customer['faxNumber'] = $params['fax'];
		}		
				
	}

	/**
	 * Builds fields for shipTo node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _build_ship_to_fields($params)
	{
		$ship_to = array();
		
		if(isset($params['ship_to_first_name']))
		{
			$ship_to['firstName'] = $params['ship_to_first_name'];
		}

		if(isset($params['ship_to_last_name']))
		{
			$ship_to['lastName'] = $params['ship_to_last_name'];
		}	

		if(isset($params['ship_to_company']))
		{
			$ship_to['company'] = $params['ship_to_company'];
		}	

		if(isset($params['ship_to_street']))
		{
			$ship_to['address'] = $params['ship_to_street'];
		}	

		if(isset($params['ship_to_city']))
		{
			$ship_to['city'] = $params['ship_to_city'];
		}	

		if(isset($params['ship_to_state']))
		{
			$ship_to['state'] = $params['ship_to_state'];
		}	

		if(isset($params['ship_to_postal_code']))
		{
			$ship_to['zip'] = $params['ship_to_postal_code'];
		}	

		if(isset($params['ship_to_country']))
		{
			$ship_to['country'] = $params['ship_to_country'];
		}	
		
		return $ship_to;	
	}

	/**
	 * Add a payment method to a request
	 * @param	string	Bank or credit card #
	 * @param	array	params
	 * @return	array	array
	*/			
	protected function _add_payment($type, $params)
	{	
		if($type === 'credit_card')
		{
			$card = array();
			
			if(isset($params['cc_number']))
			{
				$card['cardNumber'] = $params['cc_number'];
			}
			
			if(isset($params['cc_exp']))
			{
				$card['expirationDate'] = $params['cc_exp'];
			}
			
			if(isset($params['cc_code']))
			{
				$card['cardCode'] = $params['cc_code'];
			}
			
			return $card;
		}
	}	
		
	/**
	 * Make a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public static function authorize_net_oneoff_payment($params)
	{
		self::$_api_method = 'createTransactionRequest';
		self::$_request = self::_build_request($params, 'authCaptureTransaction');			
		return self::_handle_query();
	}

	/**
	 * Authorize a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public static function authorize_net_authorize_payment($params)
	{
		self::$_api_method = 'createTransactionRequest';
		self::$_request = self::_build_request($params, 'authOnlyTransaction');			
		return self::_handle_query();
	}

	/**
	 * Capture a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public static function authorize_net_capture_payment($params)
	{
		self::$_api_method = 'createTransactionRequest';
		self::$_request = self::_build_request($params, 'priorAuthCaptureTransaction');			
		return self::_handle_query();
	}

	/**
	 * Void a oneoff payment
	 * @param	array	An array of params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	 * NOTE: This transaction type can be used to cancel either an original transaction that is not yet settled, or an entire order composed of more than one transaction.  A void prevents the transaction or order from being sent for settlement. A Void can be submitted against any other transaction type.
	 * NOTE: This will ONLY work for unsettled transactions.
	*/	
	public static function authorize_net_void_payment($params)
	{
		self::$_api_method = 'createTransactionRequest';
		self::$_request = self::_build_request($params, 'voidTransaction');			
		return self::_handle_query();	
	}	

	/**
	 * Get the details for a particular transaction
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public static function authorize_net_get_transaction_details($params)
	{
		self::$_api_method = 'getTransactionDetailsRequest';
		self::$_request = self::_build_request($params);			
		return self::_handle_query();
	}	
	
	/**
	 * Refund a transaction
	 * @param	array	An array that contains your identifier
	 * @return	object	The response from the payment gateway
	 *
	 * NOTE:  This submits a LINKED credit.  Authorize.net supports both linked credits and unlinked credits.  Linked credit refunds must be submitted wthin 120 days of original settlement, and must be associated with a particular transaction.  Unlinked credits allow you to submit refunds for payments not submitted through the gateway, or beyond the 120 day period.  If you want to do unlinked credits, check this out: http://www.authorize.net/files/ecc.pdf
	*/	
	public static function authorize_net_refund_payment($params)
	{
		self::$_api_method = 'createTransactionRequest';
		self::$_request = self::_build_request($params, 'refundTransaction');		
		return self::_handle_query();	
	}	
		
	/**
	 * Create a new recurring payment
	 *
	 * @param	array
	 * @return	object
	 *
	 */		
	public static function authorize_net_recurring_payment($params)
	{
		self::$_api_method = 'ARBCreateSubscriptionRequest';
		self::$_request = self::_build_request($params);	
		return self::_handle_query();
	}	

	/**
	 * Get profile info for a particular profile id
	 *
	 * @param	array
	 * @return	object
	 */		
	public static function authorize_net_get_recurring_profile($params)
	{	
		self::$_api_method = 'ARBGetSubscriptionStatusRequest';
		self::$_request = self::_build_request($params);	
		return self::_handle_query();
	}

	/**
	 * Update a recurring payments profile
	 *
	 * @param	array
	 * @return	object
	 * NOTE:
		* The subscription start date (subscription.paymentSchedule.startDate) may only be updated in the event that no successful payments have been completed.
		¥ The subscription interval information (subscription.paymentSchedule.interval.length and subscription.paymentSchedule.interval.unit) may not be updated.
		¥ The number of trial occurrences (subscription.paymentSchedule.trialOccurrences) may only be updated if the subscription has not yet begun or is still in the trial period.
		¥ All other fields are optional.	 
	 */		
	public static function authorize_net_update_recurring_profile($params)
	{		
		self::$_api_method = 'ARBUpdateSubscriptionRequest';
		self::$_request = self::_build_request($params);		
		return self::_handle_query();
	}
	
	/**
	 * Cancel a recurring profile
	 *
	 * @param	array
	 * @return	object
	 */		
	public static function authorize_net_cancel_recurring_profile($params)
	{	
		self::$_api_method = 'ARBCancelSubscriptionRequest';
		self::$_request = self::_build_request($params);
		return self::_handle_query();
	}			

	/**
	 * Build the query for the response and call the request function
	 *
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	array
	 */		
	private static function _handle_query()
	{	
		self::$_http_query = self::$_request;
		
		$response_object = self::$payments::gateway_request(self::$_api_endpoint, self::$_http_query);
		$response = self::_parse_response($response_object);
		
		return $response;
	}

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object
	 */		
	protected function _parse_response($xml)
	{	
		$details = (object) array();

		$as_array = self::$payments::arrayize_object($xml);

		$result = $as_array['messages']['resultCode'];
		
		if(isset($as_array['transactionResponse']))
		{
			$identifier = $as_array['transactionResponse']['transId'];
		}
		
		if(isset($as_array['subscriptionId']))
		{
			$identifier = $as_array['subscriptionId'];
		}
		
		$timestamp = gmdate('c');
		$details->timestamp = $timestamp;
		$details->gateway_response = $as_array;
		
		if(isset($identifier) AND strlen($identifier) > 1)
		{
			$details->identifier = $identifier;
		}
		
		if($result == 'Ok')
		{
			return self::$payments::return_response(
				'Success',
				self::$payments::$payment_type.'_success',
				'gateway_response',
				$details
			);
		}
		
		if($result == 'Error')
		{
			if(isset($as_array['transactionResponse']['errors']['error']['errorText']))
			{
				$message = $as_array['transactionResponse']['errors']['error']['errorText'];
			}
			
			if(isset($as_array['messages']['message']['text']))
			{
				$message = $as_array['messages']['message']['text'];
			}
			
			if(isset($message))
			{
				$details->reason = $message;
			}	

			return self::$payments::return_response(
				'Failure',
				self::$payments::$payment_type.'_gateway_failure',
				'gateway_response',
				$details
			);				
		}
	}
			
		
}