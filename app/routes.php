<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

//-- Login Page
Route::get('/', 		'FrontSiteController@showIndex');

//-- Creates user controls
Route::get('start', 'ServerController@createGame');

//-- Main UI
Route::any('front', 	'FrontSiteController@showUI');

// Misc
Route::any('api/createEvent', 'ServerController@createEvent');

//* TESTING
 
Route::any('checkUserControls/{user_id}', 'ServerController@createUserControls');
Route::any('checkEvents', 'ServerController@createEvent');
//*/
Route::group(array('before' => 'auth'), function() {
	
	//-- API Routes
    Route::any('api/update/{user_id}/{control_id}/{value}', 'ServerController@checkEvent');
    Route::any('api/fail/{user_id}/{control_id}', 'ServerController@failEvent');
    
    //-- Viewing Routes
    Route::get('controls', 		'FrontSiteController@showController');
    
});

Route::get('api/users/{id?}', 'ApiUsersController@get_index');

Route::get('/profile', array('before' => 'auth', function()
{
	//@TODO: Jake to create awesome page here :)
	$user = Auth::user();
	
	echo 'Name: '.$user->firstname.' '.$user->lastname.'<br><br>';
	echo 'Achievements:<br>';
	
	foreach ($user->achievements as $achievement)
		echo $achievement->name.'<br>';
}));

Route::get('social/{action?}', array("as" => "hybridauth", function($action = "")
{
    // check URL segment
    if ($action == "auth") {
        // process authentication
        try {
            Hybrid_Endpoint::process();
        }

        catch (Exception $e) {
            // redirect back to http://URL/social/
            return Redirect::route('hybridauth');
        }
        return;
    }

    try {
        // create a HybridAuth object
        $socialAuth = new Hybrid_Auth(app_path() . '/config/hybridauth.php');
        // authenticate with Facebook
        $provider = $socialAuth->authenticate("Facebook");
        // fetch user profile
        $userProfile = $provider->getUserProfile();
       
		//Check if user has record already
        $user = User::where('email', '=', $userProfile->email)->first();
        //print_R($user);
        if ($user)
		{		    
			//Update user details
				$user->firstname = $userProfile->firstName;
				$user->lastname = $userProfile->lastName;
				$user->picture = $userProfile->photoURL;
				$user->save();
		}else{			
			$details = array(
				'email' => $userProfile->emailVerified,	
				'firstname' => $userProfile->firstName,	
				'lastname' => $userProfile->lastName,	
				'fb_id' => $userProfile->identifier,	
				'picture' => $userProfile->photoURL,	
			);
			
			$user = User::create($details);
			//echo 'User Created';
			
		}
		
		Auth::login($user, true);
		
    }

    catch(Exception $e) {
        // exception codes can be found on HybBridAuth's web site
        return $e->getMessage();
    }

    // access user profile data
    //echo "Connected with: <b>{$provider->id}</b><br />";
    //echo "As: <b>{$userProfile->displayName}</b><br />";
    //echo "<pre>" . print_r( $userProfile, true ) . "</pre><br />";
    //-- return Redirect::to('/game');
    return Redirect::to('/controls');
}));

Route::get('getphone/{action?}', array("as" => "getphone", "before" => "auth", function($action = "")
{
    // check URL segment
    if ($action == "add") {
        if($_REQUEST['number'] != ''){
			$user = Auth::user();

			$user->phone = $_REQUEST['number'];

			$user->save();
			
			return Redirect::to('/controls');
        }
    }


    echo 'Add Phone form goes here';
}));
