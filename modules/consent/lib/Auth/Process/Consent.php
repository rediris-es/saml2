<?php

/**
 * Filter for requiring the user to give consent before the attributes are released to the SP.
 *
 * The initial focus of the consent form can be set by setting the 'focus'-attribute to either
 * 'yes' or 'no'.
 *
 * Different storage backends can be configured by setting the 'store'-attribute. The'store'-attribute
 * is on the form <module>:<class>, and refers to the class sspmod_<module>_Consent_Store_<class>. For
 * examples, see the built-in modules 'consent:Cookie' and 'consent:Database', which can be found
 * under modules/consent/lib/Consent/Store.
 *
 * Example - minimal:
 * <code>
 * 'authproc' => array(
 *   'consent:Consent',
 *   ),
 * </code>
 *
 * Example - save in cookie:
 * <code>
 * 'authproc' => array(
 *   array(
 *     'consent:Consent',
 *     'store' => 'consent:Cookie',
 *   ),
 * </code>
 *
 * Example - save in MySQL database:
 * <code>
 * 'authproc' => array(
 *   array(
 *     'consent:Consent',
 *     'store' => array(
 *       'consent:Database',
 *       'dsn' => 'mysql:host=db.example.org;dbname=simplesaml',
 *       'username' => 'simplesaml',
 *       'password' => 'secretpassword',
 *       ),
 *     ),
 *   ),
 * </code>
 *
 * Example - initial focus on yes-button:
 * <code>
 * 'authproc' => array(
 *   array('consent:Consent', 'focus' => 'yes'),
 *   ),
 * </code>
 *
 * @package simpleSAMLphp
 * @version $Id$
 */
class sspmod_consent_Auth_Process_Consent extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * Where the focus should be in the form. Can be 'yesbutton', 'nobutton', or NULL.
	 */
	private $focus;


	/**
	 * Consent store, if enabled.
	 */
	private $store;


	/**
	 * Initialize consent filter.
	 *
	 * This is the constructor for the consent filter. It validates and parses the configuration.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);
		assert('is_array($config)');

		if (array_key_exists('focus', $config)) {
			$this->focus = $config['focus'];
			if (!in_array($this->focus, array('yes', 'no'), TRUE)) {
				throw new Exception('Invalid value for \'focus\'-parameter to' .
					' consent:Consent authentication filter: ' . var_export($this->focus, TRUE));
			}
		} else {
			$this->focus = NULL;
		}

		if (array_key_exists('store', $config)) {
			$this->store = sspmod_consent_Store::parseStoreConfig($config['store']);
		} else {
			$this->store = NULL;
		}

	}


	/**
	 * Process a authentication response.
	 *
	 * This function saves the state, and redirects the user to the page where the user
	 * can authorize the release of the attributes.
	 *
	 * @param array $state  The state of the response.
	 */
	public function process(&$state) {
		assert('is_array($state)');
		assert('array_key_exists("UserID", $state)');
		assert('array_key_exists("Destination", $state)');
		assert('array_key_exists("entityid", $state["Destination"])');
		assert('array_key_exists("metadata-set", $state["Destination"])');

		if ($this->store !== NULL) {
			$userId = sha1($state['UserID'] . SimpleSAML_Utilities::getSecretSalt());;
			$destination = $state['Destination']['metadata-set'] . '/' . $state['Destination']['entityid'];

			$attributeSet = array_keys($state['Attributes']);
			sort($attributeSet);
			$attributeSet = implode(',', $attributeSet);
			$attributeSet = sha1($attributeSet);

			if ($this->store->hasConsent($userId, $destination, $attributeSet)) {
				/* Consent already given. */
				return;
			}

			$state['consent:store'] = $this->store;
			$state['consent:store.userId'] = $userId;
			$state['consent:store.destination'] = $destination;
			$state['consent:store.attributeSet'] = $attributeSet;
		}

		$state['consent:focus'] = $this->focus;

		/* Save state and redirect. */
		$id = SimpleSAML_Auth_State::saveState($state, 'consent:request');
		$url = SimpleSAML_Module::getModuleURL('consent/getconsent.php');
		SimpleSAML_Utilities::redirect($url, array('StateId' => $id));
	}

}

?>