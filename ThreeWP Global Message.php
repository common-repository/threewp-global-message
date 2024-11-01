<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: ThreeWP Global Message
Plugin URI: http://mindreantre.se/threewp-global-message
Description: Displays a global message to all blogs.
Version: 0.0.2
Author: Edward Hevlund
Author URI: http://www.mindreantre.se
Author Email: edward@mindreantre.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

function dbg($variable)
{
	echo '<pre>';
	var_dump($variable);
	echo '</pre>';
}

class threewp_global_message
{
	private $isWPMU;							// Is the plugin running on a wp_mu site?
	
	private $site_options = array(
		'messages' => 'threewp_global_message_messages',
	);

	private $roles = array(
		'administrator'	=> 'manage_options',
		'editor'		=> 'manage_links',
		'author'		=> 'publish_posts',
		'contributor'	=> 'edit_posts',
		'subscriber'	=> 'read',
	); 
	
	private $default_message = '
		<div class="updated">
			<p>
				How are you gentlemen!!
			</p>
	
			<p>
				This is the default message of the <a href="http://mindreantre.se/program/threewp/threewp-global-message/">ThreeWP Global Message plugin</a>. It displays different admin panel messages to logged-in
				users depending on what user role they have. The plugin works for both WP and WPMU. To not display a message for a user role, just leave the text box for that role empty.
			</p>
		</div>
	';
	
	public function __construct()
	{
		$this->isWPMU = function_exists('is_site_admin');

		define('THREEWP_GLOBAL_MESSAGE_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/' );
		
		register_activation_hook( __FILE__, array(&$this, 'activate') );
		add_action('admin_menu', array (&$this, 'add_menu') );
		add_action('admin_notices', array(&$this, 'display'));
	}
	
	public function activate()
	{
	}
	
	public function add_menu()
	{
		if ($this->isWPMU)
		{
			if ( is_site_admin() )
				add_submenu_page('wpmu-admin.php', '3WP Global Message', '3WP Global Message', 'administrator', 'threewp_global_message', array (&$this, 'admin'));
		}
		else
		{
			if ($this->get_user_role() == 'administrator')
				add_options_page('3WP Global Message', '3WP Global Message', 'administrator', 'threewp_global_message', array (&$this, 'admin'));
		}                                                                                                                        
	}
	
	public function admin()
	{
		require_once('ThreeWP Form.php');
		$form = new threewp_form();
		
		if (isset($_POST['messageSet']))
		{
			$messages = $_POST['message'];
			$messages = self::strip_slashes_recursive($messages);
			$messages = serialize($messages);
			$messages = base64_encode($messages);
			if ($this->isWPMU)
				update_site_option($this->site_options['messages'], $messages );
			else
				update_option($this->site_options['messages'], $messages );
			echo '<p class="updated fade">The messages have been saved.</p>';
		}
		
		if (isset($_POST['uninstall']))
		{
			if ($this->isWPMU)
				delete_site_option($this->site_options['messages']);
			else
				delete_option($this->site_options['messages']);
			echo '<p class="updated fade">Database has been cleaned. You may now uninstall the plugin.</p>';
		}
		
		$inputs = array(
			'message' => array(),
			'messageSet' => array(
				'name' => 'messageSet',
				'cssClass' => 'button-primary',
				'type' => 'submit',
				'value' => 'Set the messages',
			),
			'uninstallSure' => array(
				'name' => 'uninstallSure',
				'type' => 'checkbox',
				'label' => 'I am sure I want to remove the database settings.',
				'value' => 0,
			),
			'uninstall' => array(
				'name' => 'uninstall',
				'type' => 'submit',
				'value' => 'Remove ThreeWP Global Message\'s database settings.',
				'cssClass' => 'button-secondary',
			),
		);
		
		$messages = $this->getMessages();
		$roles = array_keys($this->roles);
		foreach($roles as $role)
		{
			if ($messages === false)
				$value = $this->default_message;
			elseif (isset($_POST['messages'][$role]))
				$value = $_POST['messages'][$role];
			else
				$value = $messages[$role];
				
			$inputs['message'][] = array(
				'name' => $role,
				'type' => 'textarea',
				'nameprefix' => '[message]',
				'label' => 'Message to show to <strong>' . $role . 's</strong>',
				'value' => htmlspecialchars($value),
				'cols' => 80,
				'rows' => 5,
				'validation' => array(
					'empty' => true,
				),
				'preview' => $value,				// Not an offical input option.
			);
		}
				
		echo '
			'.$form->start().'
			<h2>ThreeWP Global Message</h2>

			<p>
				Displays global messages to logged-in users in the administration panel. The messages are HTML-friendly and not filtered in any way.
			</p>
		';
		
		foreach($inputs['message'] as $input)
			echo '<div class="'.$input['name'].'" style="overflow: hidden; padding-bottom: 5em;"><p>'.$form->makeLabel($input).'<br />'.$form->makeInput($input).'<br />Preview:<br />'.$input['preview'].'</p></div>';

		echo '
			<p>
				'.$form->makeInput($inputs['messageSet']).'
			</p>
			'.$form->stop().'

			'.$form->start().'
			<h3>Uninstall</h3>

			<p>
				If you want to clean the database of ThreeWP Global Message settings, use the checkbox and button below. Be warned that your stored global messages will disappear.
			</p>

			<p>
				'.$form->makeInput($inputs['uninstallSure']).' '.$form->makeLabel($inputs['uninstallSure']).'<br />
				'.$form->makeInput($inputs['uninstall']).'
			</p>

			'.$form->stop().'
		';
	}
	
	/**
	 * Displays a message.
	 */
	public function display()
	{
		$role = $this->get_user_role();
		
		$messages = $this->getMessages();
		
		if ($messages[$role] != '')
			echo $messages[$role];
	}
	
	/**
	 * Returns the user's role as a string.
	 */
	private function get_user_role()
	{
		foreach($this->roles as $role=>$capability)
			if (current_user_can($capability))
				return $role;
	}
	
	/**
	 * Gets the settings from the database. Decodes and unserializes them.
	 */
	private function getMessages()
	{
		if ($this->isWPMU)
			$messages = get_site_option($this->site_options['messages']);
		else
			$messages = get_option($this->site_options['messages']);
		$messages = base64_decode($messages);
		$messages = unserialize($messages);
		return $messages;
	}
	
	/**
	 * http://php.net/manual/en/function.stripslashes.php
	 * 
	 * shredder at technodrome dot com
	 */
	private static function strip_slashes_recursive( $variable )
	{
	    if ( is_string( $variable ) )
	        return stripslashes( $variable ) ;
	    if ( is_array( $variable ) )
	        foreach( $variable as $i => $value )
	            $variable[ $i ] = self::strip_slashes_recursive( $value ) ;
	   
	    return $variable ;
	}
}

global $threewp_global_message;
$threewp_global_message = new ThreeWP_Global_Message();

?>