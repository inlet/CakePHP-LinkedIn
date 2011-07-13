Written in collaboration with NoProtocol http://www.noprotocol.nl/
Special thanks to:

+ Stef van den Ham
+ Bob Fanger
+ Paul Stomp

# LinkedIn plugin for CakePHP

This plugin provides a simple and solid bridge between CakePHP and LinkedIn API. No need to know the complexity of OAuth, just add the plugin to your plugins directory and you can use the LinkedIn API out of the box. This plugin is written on top of the OAuth lib by Cakebaker (http://code.42dh.com/oauth/). The good part is that you can use this plugin anywhere in your application. No need to create a datasource in conjunction with a database configuration.

# Installation
To give you an example, here are the steps to make it work!

### Step 1: Download / clone the plugin in your cake plugin directory `app/plugins/linkedin`
Basically this is all you need. Your cake plugin structure should look like this

```
app/
	plugins/
		linkedin/
			controllers/
			vendors/
```

### Step 2: Use the plugin in your controller
Setup the component, provide your application key and secret

```
class MyController extends AppController {

	var $components = array('Linkedin.Linkedin' => array(
		'key' => 'YOUR API KEY HERE',
		'secret' => 'YOUR API SECRET HERE',
	));
	
}
```
### Step 3: Do authorization

```
	/**
	 * start connecting..
	 */
	public function index() {
		$this->Linkedin->connect( /* optionally provide a custom callback url -> array('action'=>'custom_connect_callback') */ );
	}
	
	/**
	 * Default callback: request token successful requested.
	 * Now try to exchange request token in access token..
	 */
	public function linkedin_connect_callback() {
		$this->Linkedin->authorize( /* optionally provide a custom callback url -> array('action'=>'custom_authorize_callback') */ );
	}
	
	/**
	 * Default callback: we're successfully connected with linkedin API
	 */
	public function linkedin_authorize_callback() {
		// we are successfully connected with linkedin API, now you can call any API method you like and retrieve the data you want
	}
```
### Step 4: Using the API methods
Now you are connected, you can make any API call you want. 

For a full overview of the methods see the linkedin developer reference: http://developer.linkedin.com/docs/DOC-1258.

For example, let's retrieve the profile info of the connected user:

```
	public function profile() {
		$this->set('response', $this->Linkedin->call('people/~',
													 array(
														  'id',
														  'picture-url',
														  'first-name', 'last-name', 'summary', 'specialties', 'associations', 'honors', 'interests', 'twitter-accounts',
														  'positions' => array('title', 'summary', 'start-date', 'end-date', 'is-current', 'company'),
														  'educations',
														  'certifications',
														  'skills' => array('id', 'skill', 'proficiency', 'years'),
														  'recommendations-received',
													 )));
	}
```

### Step 5: Plugin methods you must know about
```
/**
 * Connect to linkedin and create request token
 *
 * @param $redirectUrl (optional) provide a custom callback method, example: connect( array('controller' => 'mycontroller', 'action' => 'custom_connect_callback') )
 * NOTE: when no redirect is provided, the default callback 'linkedin_connect_callback' in controller will be used.
 */
connect($redirectUrl = null)

/**
 * Exchange request token to access token
 *
 * @param $redirectUrl (optional) provide a custom callback method, example: connect( array('controller' => 'mycontroller', 'action' => 'custom_authorize_callback') )
 * NOTE: when no redirect is provided, the default callback 'linkedin_authorize_callback' in controller will be used.
 */
authorize($redirectUrl = null)

/**
 * API call to GET linkedin data.
 * 
 * @see http://developer.linkedin.com/docs/DOC-1258
 * @param $path API call method, example: 'people/~'
 * @param $args array of fields to provide.
 * @return response array|null
 */
call($path, $args)

/**
 * API call to POST data
 * 
 * @see http://developer.linkedin.com/docs/DOC-1258
 * @param $path API call method, example: 'people/~/mailbox'
 * @param $data  array/object for json or an string for xml/json
 * @param string $type  "json" or "xml"
 * @return array|null response
 */
send($path, $data, $type = 'json')

/**
 * Are we connected to the linkedin API?
 *
 * @return bool
 */
isConnected()
```




