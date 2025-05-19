<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';
error_reporting(E_ALL & ~E_DEPRECATED); 

$userid = get_current_user_id();

$user 		= get_user_by( 'id', $userid );
$username 	= $user->user_login;
$userEmail 	= $user->user_email;

if (!session_id()) {
    session_start();
}

$cache_key = isset($_GET['cache_key']) ? sanitize_text_field($_GET['cache_key']) : 'drmx_temp_params_' . session_id();
wp_cache_delete($cache_key, 'options');
$drmx_params = get_option($cache_key);

$flag	= 0;
// Get the yourproductid set in the license Profile.
$PIDs	= explode('-', $drmx_params["yourproductid"]);

// Get integration parameters
define( 'DRMX_ACCOUNT', 		get_option('drmx_account'));
define( 'DRMX_AUTHENTICATION', 	get_option('drmx_authentication'));
define( 'DRMX_GROUPID', 		get_option('drmx_groupid'));
define( 'DRMX_RIGHTSID', 		get_option('drmx_rightsid'));
define( 'WSDL', 				get_option('drmx_wsdl'));
define( 'DRMX_BINDING', 		get_option('drmx_binding'));

$client = new SoapClient(WSDL, array('trace' => false));

/********* Business logic verification ***********/

// Get user's completed course orders
$user_course_orders = get_user_completed_course_orders($userid);

// Get completed course IDs and add to array
$purchased_course_ids = [];
foreach ($user_course_orders as $order) {
    $purchased_course_ids[] = $order->course_id;
}

// Traverse the course ID array and check if there is a matching ID
foreach ($PIDs as $course_id) {
    if (in_array($course_id, $purchased_course_ids)) {
		
        // If the username is not exists, call 'AddNewUser' to add user.
		if(checkUserExists($client, $username) == "False"){
			$addNewUserResult = addNewUser($client, $username, $userEmail);
			$info = $addNewUserResult;
			$flag = 1;
		}

		// check user is revoked
		/*if(checkUserIsRevoked($client, $username)){
			$errorInfo = 'Username: '.$username.' is revoked.';
			header("location:drmx_LicError.php?error=".$errorInfo."&message=".$message);
			exit;
		}*/
		
		/*** Obtaining course duration ***/
		$duration = get_lp_course_duration_in_days($course_id);
		
		/*** Automatically update license permissions for users based on duration of the course ****/
		$updateRightResult = updateRight($client, $duration, $userEmail);

		if($updateRightResult == '1'){
			
			/*****After the License Rights is updated, perform the method of obtaining the license****/
			$licenseResult = getLicense($client, $drmx_params, $username);
			$license = $licenseResult->getLicenseRemoteToTableWithVersionWithMacResult;
			$message = $licenseResult->Message;

			if(stripos($license, '<div id="License_table_DRM-x4" style="display:none;">' )  === false ){
				header('location: drmx_LicError.php?error='.$license.'&message='.$message);
				exit;
			}
			/*****After obtaining the license, store the license and message through the session, and then direct to the licstore page******/
			
			$license_params = [
				'license'    	=> isset($license) ? $license : '',
				'message'   	=> isset($message) ? $message : '',
				'return_url'   	=> $drmx_params["return_url"],
			];
			$license_cache_key = 'license_temp_params_' . session_id();
			wp_cache_delete($license_cache_key, 'options');
			update_option($license_cache_key, $license_params, false);


			header('location: licstore.php');
			$flag = 1;
			$info = "Getting license...";
			exit;
		}else{
			$info = $updateRightResult;
			$flag = 1;
			break;
		}
    }
}

if($flag == 0) {
	$info = "You have not purchased this course yet, can not open this file! ProfileID: ". $drmx_params["profileid"]. " YourproductID: " . $drmx_params["yourproductid"];
}


/***** End of business logic verification ******/
// Get user's completed course orders
function get_user_completed_course_orders($user_id) {
    global $wpdb;
    
    $query = $wpdb->prepare(
        "SELECT 
            ui.item_id AS course_id,
            o.ID AS order_id
        FROM 
            {$wpdb->prefix}learnpress_user_items ui
            INNER JOIN {$wpdb->prefix}posts o ON (
                o.ID = ui.ref_id 
                AND o.post_type = 'lp_order' 
                AND o.post_status = 'lp-completed'
            )
        WHERE 
            ui.user_id = %d 
            AND ui.item_type = 'lp_course'
        GROUP BY 
            ui.item_id, o.ID",
        $user_id
    );
    
    return $wpdb->get_results($query);
}

// Get course duration
function get_lp_course_duration($course_id) {
    $duration_str = get_post_meta($course_id, '_lp_duration', true);
    
    if (preg_match('/^([\d\.]+)\s*([a-zA-Z]*)$/', trim($duration_str), $matches)) {
        $value = is_numeric($matches[1]) ? $matches[1] : null;
        $unit  = strtolower($matches[2] ?? '');
        
        $value = (float)$value;
        $value = ($value == (int)$value) ? (int)$value : $value;

        $unit = ucfirst($unit);

        return [
            'value' => $value,
            'unit'  => $unit
        ];
    }
}

// Get course duration (convert weeks to days)
function get_lp_course_duration_in_days($course_id) {
    $duration = get_lp_course_duration($course_id);
    
    if ($duration['unit'] === 'Week' || $duration['unit'] === 'Weeks') {
        return [
            'value' => $duration['value'] * 7,
            'unit'  => 'Day'
        ];
    }
    
    return $duration;
}

/********DRM-X 4.0 functions********/
function getIP(){
    static $realip;
    if (isset($_SERVER)){
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")){
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    return $realip;
}

function checkUserExists($client, $username) {
    $CheckUser_param = array(
        'UserName'				=> $username,
        'AdminEmail'			=> DRMX_ACCOUNT,
        'WebServiceAuthStr'		=> DRMX_AUTHENTICATION,
    );
    
    $CheckUser = $client->__soapCall('CheckUserExists', array('parameters' => $CheckUser_param));
    return $CheckUser->CheckUserExistsResult;
}

function addNewUser($client, $username, $userEmail){
	$add_user_param = array(
		'AdminEmail'		=> DRMX_ACCOUNT,
		'WebServiceAuthStr'	=> DRMX_AUTHENTICATION,
		'GroupID'			=> DRMX_GROUPID,
		'UserLoginName'		=> $username,
		'UserPassword'		=> 'N/A',
		'UserEmail'			=> $userEmail,
		'UserFullName'		=> 'N/A',
		'Title'				=> 'N/A',
		'Company'			=> 'N/A',
		'Address'			=> 'N/A',
		'City'				=> 'N/A',
		'Province'			=> 'N/A',
		'ZipCode'			=> 'N/A',
		'Phone'				=> 'N/A',
		'CompanyURL'		=> 'N/A',
		'SecurityQuestion'	=> 'N/A',
		'SecurityAnswer'	=> 'N/A',
		'IP'				=> getIP(),
		'Money'				=> '0',
		'BindNumber'		=> DRMX_BINDING,
		'IsApproved'		=> 'yes',
		'IsLockedOut'		=> 'no',
	);

	$add_user = $client->__soapCall('AddNewUser', array('parameters' => $add_user_param));
	return $add_user->AddNewUserResult;
}

function updateRight($client, $duration, $userEmail){
	$beginDate = date("Y/m/d", strtotime("-2 days"));
	$ExpirationDate = date("Y/m/d", strtotime("+1 year"));
	
	$updateRight_param = array(
		'AdminEmail'				=> DRMX_ACCOUNT,
		'WebServiceAuthStr'			=> DRMX_AUTHENTICATION,
		'RightsID'					=> DRMX_RIGHTSID,
		'Description'				=> "Courses Rights (Please don't delete)",
		'PlayCount'					=> "-1",
		'BeginDate'					=> $beginDate,
		'ExpirationDate'			=> $ExpirationDate,
		'ExpirationAfterFirstUse'	=> $duration['value'],
		'RightsPrice'				=> "0",
		'AllowPrint'				=> "False",
		'AllowClipBoard'			=> "False",
		'AllowDoc'					=> "False",
		'EnableWatermark'			=> "True",
		'WatermarkText'				=> $userEmail." ++username",
		'WatermarkArea'				=> "1,2,3,4,5,",
		'RandomChangeArea'			=> "True",
		'RandomFrquency'			=> "12",
		'EnableBlacklist'			=> "True",
		'EnableWhitelist'			=> "True",
		'ExpireTimeUnit'			=> $duration['unit'],
		'PreviewTime'				=> 3,
		'PreviewTimeUnit'			=> "Day",
		'PreviewPage'				=> 3,
		'DisableVirtualMachine'		=> 'True',
	);
	$update_Right = $client->__soapCall('UpdateRightWithDisableVirtualMachine', array('parameters' => $updateRight_param));
	return $update_Right->UpdateRightWithDisableVirtualMachineResult;
}

function checkUserIsRevoked($client, $username){
	$CheckUserIsRevoked = array(
		'AdminEmail'         => DRMX_ACCOUNT,
		'WebServiceAuthStr'  => DRMX_AUTHENTICATION,
		'UserLoginName'      => $username,
	);
	$CheckUserIsRevokedResult = $client->__soapCall('CheckUserIsRevoked', array('parameters' => $CheckUserIsRevoked));
	return $CheckUserIsRevokedResult->CheckUserIsRevokedResult;
}

function getLicense($client,$drmx_params, $username){
	
	$param = array(
		'AdminEmail'         => DRMX_ACCOUNT,
		'WebServiceAuthStr'  => DRMX_AUTHENTICATION,
		'ProfileID'          => $drmx_params["profileid"],
		'ClientInfo'         => $drmx_params["clientinfo"],
		'RightsID'           => DRMX_RIGHTSID,
		'UserLoginName'      => $username,
		'UserFullName'       => 'N/A',
		'GroupID'            => DRMX_GROUPID,
		'Message'            => 'N/A',
		'IP'                 => getIP(),
		'Platform'           => $drmx_params["platform"],
		'ContentType'        => $drmx_params["contenttype"],
		'Version'            => $drmx_params["version"],
		'Mac'            	 => $drmx_params["mac"],
	);
	
	/*****Obtain license by calling getLicenseRemoteToTableWithVersion******/
	$result = $client->__soapCall('getLicenseRemoteToTableWithVersionWithMac', array('parameters' => $param));
	return $result;
}

?>

<!DOCTYPE html>
<html>
<head>
 <meta http-equiv="content-type" content="text/html; charset=UTF-8">
 <title>Login and obtain license</title>
 <link rel="stylesheet" type="text/css" href="public/css/login-style.css" />
</head>

<body>
	<div class="login-wrap">
       	<div class="login-head">
          	<div align="center"><img src="public/images/logo.png" alt=""></div>
       	</div>
		<div class="login-cont">
			<div id="btl-login-error" class="btl-error">
				<div class="black">
					<?php 
						echo esc_attr($info);
					?>
				</div>
			</div>
			<div class="login-foot">
				<div class="foot-tit">Other options</div>
				<div class="foot-acts">
					<a class="link-reg" href="<?php echo esc_attr(site_url()); ?>" target="_blank">Help?</a>
					<a class="link-get-pwd" href="<?php echo esc_attr(site_url()); ?>" target="_blank">Buy Course</a>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
