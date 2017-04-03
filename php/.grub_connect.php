<?php
  // Class used to interface with mysql database on CSX.
  // Previously I was including connect.php then allowing other php files to access a public mysql connection object.
  // Instead of doing this, other php files will have interface with the database via a dbconnection object.
  
  
  
/*
  NOTICE:
		->	Private functions are NOT obligated to escape input. It is the resposibility
			of any public dbconnection function to escape input before calling a
			private function.
		->	Private functions are NOT obligated to check if _db_connection is open. It is
			the responsibility of public dbconnection functions to ensure a connection is
			open before calling private functions.
*/
  class dbconnection {  
		
		private $_isopen; //if true then connection is open
		private $_db_connection; //mysql database connection
		private $_min_password_len;
		private $_valid_user_types;
		private static $_config_path = ""; //enter path to configuration file
		function __construct() {
			$this->_isopen = false;
			$config = parse_ini_file(self::$_config_path);
			$this->_db_connection = mysqli_connect(
				$config['servername'],
				$config['username'],
				$config['password'],
				$config['dbname']);
			$this->_min_password_len = 6;
			$this->_valid_user_types = array("diner", "restaurant");
			if(!$this->_db_connection) {
				echo '<p>Could not connect to MySQL </p>' . mysqli_error();
				$this->_isopen = false;
			} else {
				$this->_isopen = true;
			}
		}
		function __destruct() {
			if($this->_isopen) {
				mysqli_close($this->_db_connection);
			}
		}
		
		public function get_table($table_name) {
			if(!$this->_isopen) {
				echo 'database connection is not open.';
				return false;
			}
			switch($table_name) {
			case "user":
				return mysqli_query($this->_db_connection, "SELECT * FROM active_user_v");
			case "diner":
				return mysqli_query($this->_db_connection, "SELECT * FROM active_diner_v");
			default:
				echo $table_name . ' is not a valid table name.';
				return false;
			}
		}
		
		private function chk_and_esc($txt, $err_msg = "Value cannot be blank.") {
			if($txt == "") {
				echo $err_msg;
				return false;
			} else {
				if(!$result = mysqli_real_escape_string($this->_db_connection, $txt)) {
					echo "<p>" . $this->get_error() . "</p>";
					return false;
				} else {
					return $result;
				}
			}
        }
		private function cln_usr_input(&$name, &$email, &$password, &$type) {
			if(!$name = $this->chk_and_esc($name, "<p>Name cannot be blank.</p>")) { //make sure the name is not blank
				return false;
			}
			if(!$email = $this->chk_and_esc($email, "<p>Email cannot be blank.</p>")) { //make sure the email is not blank
				return false;
			}
			//make sure the password is not blank
			if(!$password = $this->chk_and_esc($password, "<p>Password cannot be blank.</p>")) {
				return false;
			} elseif(strlen($password) < $this->_min_password_len) {
				echo "<p>Password must be at least $this->_min_password_len characters long.</p>";
				return false;
			} else {
				$password = password_hash($password, PASSWORD_DEFAULT);
			}
			//make sure the type is not blank. Also type is not escaped because the value must be strictly matched values in _valid_user_types()
			if(!$type = $this->chk_and_esc($type, "<p>User type cannot be blank.")) {	
				return false;
			} else {
				if($this->valid_user_types[array_search($type, $this->_valid_user_types, true)] == $type) {
					echo "<p>User type $type is not a valid type.</p>";
					return false;
				}
			}
			return true;
		}
		
		public function add_user($name, $email, $password, $type) {
			//check if the database is open
			if(!$this->_isopen) {
				echo ' database connection is not open';
				return false;
			}
			//validate and cleanup input
			if(!$this->cln_usr_input(
				$name,
				$email,
				$password,
				$type)) {
					echo "<p>Sign up failed.</p>";
					return false;
			}
			$stmt = mysqli_prepare($this->_db_connection, "INSERT INTO user VALUES(NULL, TRUE, NOW(), NOW(), ?, ?, ?, ?)");
			$stmt->bind_param('ssss', $type, $name, $email, $password);
			return($stmt->execute());
		}
		
		public function user_search($target, $target_type, $exact_match) {
			if(!$this->_isopen) {
				echo ' database connection is not open';
				return false;
			}
			//escape user input
			$target = mysqli_real_escape_string($this->_db_connection, $target);
			switch($target_type) {
				case "name":
					if($exact_match) {
						$stmt = mysqli_prepare($this->_db_connection, "SELECT * FROM active_user_v WHERE user_name = ?");
						$stmt->bind_param('s', $target);
						$stmt->execute();
						
						return mysqli_query($this->_db_connection, "SELECT * FROM active_user_v WHERE user_name = '$target'");
					} else {
						return mysqli_query($this->_db_connection, "SELECT * FROM active_user_v WHERE user_name LIKE '%$target%'");
					}
					break;
				default:
					echo ' Invalid target type' . "\n";
					return false;
					break;
			}
		}
		
		//returns user id if user is found and false if user name + password combo is not found
		public function validate_user($username, $password, $only_active = true) {
			if(!$this->_isopen) {
				echo "<p>database connection is not open</p>'";
				return false;
			}
			if(!$username = $this->chk_and_esc($username, "<p>Username cannot be blank.</p>")) {
				return false;
			}
			if(!$password = $this->chk_and_esc($password, "<p>Password cannot be blank.</p>")) {
				return false;
			}
			if($only_active) {
				$stmt = mysqli_prepare($this->_db_connection, "SELECT password FROM user WHERE is_active = TRUE AND user_name = ?");
			} else {
				$stmt = mysqli_prepare($this->_db_connection, "SELECT password FROM user WHERE user_name = ?");
			}
			$stmt->bind_param('s', $username);
			$stmt->execute();
			if(!$stmt->execute()) {
				echo "<p>$username does not exist or is inactive.</p>";
				return false;
			}
			$result = $stmt->get_result();
			if($result->num_rows <= 0) {
				echo "<p>User not found.</p>";
				return false;
			}
			$row = mysqli_fetch_array($result);
			$hash = $row['password'];
			return password_verify($password, $hash);
		}
		
		//input check and escaping should be performed.
		private function update_login($id) {
			$stmt = mysqli_prepare($this->_db_connection, "UPDATE user SET last_login = NOW() WHERE user_id = ?");
			$stmt->bind_param('i',$id);
			return $stmt->execute();
		}
		
		private function get_userid($username, $only_active = true) {
			if($only_active) {
				$stmt = mysqli_prepare($this->_db_connection, "SELECT user_id FROM active_user_v WHERE user_name = ?");
			} else {
				$stmt = mysqli_prepare($this->_db_connection, "SELECT user_id FROM user_v WHERE user_name = ?");
			}
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$result = $stmt->get_result();
			$id = mysqli_fetch_array($result);
			return $id['user_id'];
		}
		
		public function login($username, $password) {
				//validate user will check and escape input, then verify existing user
				if(!$this->validate_user($username, $password)) {
					echo "<p>Login failed. Password does not match user name.</p>";
					return false;
				}
				if(!$id = $this->get_userid($username)) {
					echo "<p>Login failed. User id was not returned for given user name $username. </p>";
					return false;
				}
				if(!$this->update_login($id)) {
					echo "<p>Login failed. Last login date failed to be updated.</p>";
					return false;
				}
				return $id;
		}
		
		
		public function get_error() {
			if($this->_isopen) {
				return mysqli_error($this->_db_connection);
			} else {
				return "";
			}
		}
		
		public function get_entree($entree_id) {
			if(!$this->_isopen) {
				return false;
			}
			$id = mysqli_real_escape_string($this->_db_connection, $entree_id);
			$stmt = mysqli_prepare($this->_db_connection, "SELECT * FROM entree WHERE entree_id = ?");
			$stmt->bind_param('i', $id);
			$stmt->execute();
			if($stmt->num_rows <= 0) {
				return false;
			} else {
				return $stmt->get_result();
			}
		}
		
		public function get_restaurant($username, $password) {
			if(!$user = $this->get_user($username, $password)) {
				echo "<p>Username and password combination is invalid. Failed to get restaurant.</p>";
				return false;
			}
			$result = mysqli_fetch_array($user);
			$user_id = mysqli_real_escape_string($this->_db_connection, $result['user_id']);
			$stmt = mysqli_prepare($this->_db_connection, "SELECT * FROM restaurant WHERE user_id = ?");
			$stmt->bind_param('i', $user_id);
			$stmt->execute();
			$result = $stmt->get_result();
			return mysqli_fetch_array($result);
		}
		
		public function get_image($image_id) {
			if(!$this->_isopen) {
				return false;
			}
			$id = mysqli_real_escape_string($this->_db_connection, $image_id);
			$stmt = mysqli_prepare($this->_db_connection, 
				"SELECT a.image_source_dir, b.image_name FROM image as 'a', image_source as 'b' WHERE 
				 a.image_source_id = b.image_source_id AND a.image_id = ?");
			$stmt->bind_param('i', $id);
			$stmt->execute();

			if($stmt->num_rows <= 0) {
				return false;
			} else {
				return $stmt->get_result();
			}
		}
		
		private function reconnect() {
			$config = parse_ini_file(self::$_config_path);
			$this->_db_connection = mysqli_connect(
				$config['servername'],
				$config['username'],
				$config['password'],
				$config['dbname']);
			if(!$this->_db_connection->ping()) {
				echo "<p>Connection Error: " . mysqli_error($this->_db_connection) . "</p>";
				return false;
			} else {
				return true;
			}	
		}
		
		private function connection_ok() {
			if(!$this->_db_connection->ping()) {
				return $this->reconnect();
			} else {
				return true;
			}
		}
		
		public function add_location(
			$rest_id,
			$name,
			$country,
			$state,
			$city,
			$street,
			$zip,
			$phone) {
				if(!$this->connection_ok()) {
					return false;
				}
				$rest_id_s = mysqli_real_escape_string($this->_db_connection, $rest_id);
				$name_s = mysqli_real_escape_string($this->_db_connection, $name);
				$country_s =  mysqli_real_escape_string($this->_db_connection,$country);
				$state_s = mysqli_real_escape_string($this->_db_connection,$state);
				$city_s =  mysqli_real_escape_string($this->_db_connection,$city);
				$street_s =  mysqli_real_escape_string($this->_db_connection,$street);
				$zip_s =  mysqli_real_escape_string($this->_db_connection,$zip);
				$phone_s =  mysqli_real_escape_string($this->_db_connection,$phone);
				$stmt = mysqli_prepare(
					$this->_db_connection,
					"INSERT INTO location VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
				$stmt->bind_param("isssssss",$rest_id_s, $name_s, $country_s, $state_s, $city_s, $street_s, $zip_s, $phone_s);
				return $stmt->execute();
		}
		
		private function get_user_byid($user_id) {
			if(!$this->connection_ok()) {
				return false;
			}
			$stmt = mysqli_prepare($this->_db_connection, "SELECT * FROM user_v WHERE user_id = ?");
			$stmt->bind_param('i',$user_id);
			$stmt->execute();
			return $stmt->get_result();
		}
		
		public function get_usertype($user_id) {
			if(!$user = $this->get_user_byid($user_id)) {
				return false;
			}elseif($user->num_rows <=0) {
				echo "<p>$user_id was not found</p>";
				return false;
			}
			$result = mysqli_fetch_array($user);
			return $result['user_type'];
		}
		
		public function get_user($username, $password) {
			if(!$this->_db_connection->ping()) {
				echo "<p>Database connection is closed. Failed to get user.</p>";
				return false;
			}
			if(!$this->validate_user($username, $password, false)) {
				echo "<p>Failed to validate user. Username and password combination is invalid.</p>";
				return false;
			}
			if(!$user_id = $this->get_userid($username)) {
				echo "<p>Failed to get user id. User cannot be retrieved.</p>";
				return false;
			}
			$stmt = mysqli_prepare($this->_db_connection, "SELECT * FROM user_v WHERE user_id = ?");
			$stmt->bind_param('i', $user_id);
			$stmt->execute();
			return $stmt->get_result();
		}
		
  }
 
  class entree {
		private $_entree_info;
		private $_image_dir;
		private $_image_name;
		private $_has_image;
		private $_image_path;
		private $_dbc;
		public function __construct($entree_id) {
			$this->_dbc = new dbconnection;
			if(!$this->_entree_info = $this->_dbc->get_entree($entree_id)) {
				die("Entree was not found.");
			}
			if($image_info = $this->_dbc->get_image($this->_entree_info['image_id'])) {
				$this->_image_dir = $image_info['image_source_dir'];
				$this->_image_name = $image_info['image_name'];
				if($this->_image_dir[strlen($this->_image_dir)-1] != '\\' and $this->_image_dir[strlen($this->_image_dir)-1] != '/') {
					$this->_image_dir += '/';
				}
				if($this->_image_name[0] == '\\' or $this->_image_name[0] == '/') {
					$this->_image_name = substr($this->_image_name, 1);
				}
				$this->_image_path = $this->_image_dir . $this->_image_name;
				$this->_has_image = true;
			} else {
				$this->_image_dir = "";
				$this->_image_name = "";
				$this->_image_path = "";
				$this->_has_image = false;
			}
		}
		public function id() {
			return $this->_entree_info['entree_id'];
		}
		public function location_id() {
			return $this->_entree_info['location_id'];
		}
		public function description() {
			return $this->_entree_info['description'];
		}
		public function image_id() {
			return $this->_entree_info['image_id'];
		}
		public function img_dir() {
			if($this->_has_image) {
				return $this->_image_dir;
			} else {
				return false;
			}
		}
		public function img_name() {
			if($this->_has_image) {
				return $this->_image_name;
			} else {
				return false;
			}
		}
		public function img_path() {
			if($this->_has_image) {
				return $this->_image_path;
			} else {
				return false;
			}
		}
		

  }
  
  class location_info {
	  public $name;
	  public $country;
	  public $state;
	  public $city;
	  public $street;
	  public $zip;
	  public $phone;
	  /*public function __construct(
		$init_name,
		$init_country,
		$init_state,
		$init_city,
		$init_street,
		$init_zip,
		$init_phone) {
				$this->name = $init_name;
				$this->country = $init_country;
				$this->state = $init_state;
				$this->city = $init_city;
				$this->street = $init_street;
				$this->zip = $init_zip;
				$this->phone = $init_phone;
		}*/
  }
  
  class restaurant {
	  private $_dbc;
	  private $_info;
	  
	  public function __construct($username, $password) {
		  $this->_dbc = new dbconnection;
		  if(!$this->_info = $this->_dbc->get_restaurant($username, $password)) {
			  die("Username and password combination was invalid or $username is not a restaurant.</p>");
		  }
	  }
	  public function add_location(&$loc_info) {
		  return $this->_dbc->add_location(
			$this->_info['restaurant_id'],
			$loc_info->name,
			$loc_info->country,
			$loc_info->state,
			$loc_info->city,
			$loc_info->street,
			$loc_info->zip,
			$loc_info->phone);
	  }
	  
  }
?>