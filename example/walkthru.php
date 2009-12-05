<?php

error_reporting(E_ALL);
require_once dirname(__FILE__)."/../lib/fireeagle.php";

function main() {
	// hardcode your keys here
	$fe_key = 'INSERT CONSUMER KEY HERE';
	$fe_secret = 'INSERT CONSUMER SECRET HERE';

	// or put them in walkthru_config.php, if you don't want to change this file

    //Hardcode your application callback URL here, by uncommenting the 
    //last line of this comment block. Don't forget to put '?f=callback' to
    //the URL. The URL will typically be the same as the one for the main page.
    //$fe_callback = 'INSERT CALLBACK URL HERE';
    if (!isset($fe_callback)) {
        echo "ERROR! Did not get a Auth callback. Did you set the fe_callback variable in the server?";
        exit;
    }

	$cfn = dirname(__FILE__)."/walkthru_config.php";
	if (file_exists($cfn)) require_once($cfn);
	
	ob_start();
	session_start();
 
	if (@$_GET['f'] == 'start') {
		// get a request token + secret from FE and redirect to the authorization page
		// START step 1
		$fe = new FireEagle($fe_key, $fe_secret);
		$tok = $fe->getRequestToken($fe_callback);
		if (!isset($tok['oauth_token'])
		    || !is_string($tok['oauth_token'])
		    || !isset($tok['oauth_token_secret'])
		    || !is_string($tok['oauth_token_secret'])) {
			echo "ERROR! FireEagle::getRequestToken() returned an invalid response.  Giving up.";
			exit;
		}
		$_SESSION['auth_state'] = "start";
		$_SESSION['request_token'] = $token = $tok['oauth_token'];
		$_SESSION['request_secret'] = $tok['oauth_token_secret'];
		header("Location: ".$fe->getAuthorizeURL($token));
		// END step 1
	} else if (@$_GET['f'] == 'callback') {
		// the user has authorized us at FE, so now we can pick up our access token + secret
		// START step 2
		if (@$_SESSION['auth_state'] != "start") {
			echo "Out of sequence.";
			exit;
		}
		if ($_GET['oauth_token'] != $_SESSION['request_token']) {
			echo "Token mismatch.";
			exit;
		}
        if ((FireEagle::$FE_OAUTH_VERSION == OAUTH_VERSION_10A)
            && !isset($_GET['oauth_verifier'])) {
            echo "OAuth protocol error. No verifier in response.";
            exit;
        }

		$fe = new FireEagle($fe_key, $fe_secret, $_SESSION['request_token'], $_SESSION['request_secret']);
		$tok = $fe->getAccessToken($_GET['oauth_verifier']);
		if (!isset($tok['oauth_token']) || !is_string($tok['oauth_token'])
		    || !isset($tok['oauth_token_secret']) || !is_string($tok['oauth_token_secret'])) {
			error_log("Bad token from FireEagle::getAccessToken(): ".var_export($tok, TRUE));
			echo "ERROR! FireEagle::getAccessToken() returned an invalid response.  Giving up.";
			exit;
		}

		$_SESSION['access_token'] = $tok['oauth_token'];
		$_SESSION['access_secret'] = $tok['oauth_token_secret'];
		$_SESSION['auth_state'] = "done";
		header("Location: ".$_SERVER['SCRIPT_NAME']);
		// END step 2
	} else if (@$_SESSION['auth_state'] == 'done') {
		// we have our access token + secret, so now we can actually *use* the api
		// START step 3
		$fe = new FireEagle($fe_key, $fe_secret, $_SESSION['access_token'], $_SESSION['access_secret']);

		// handle postback for location update
		if ($_SERVER['REQUEST_METHOD'] == "POST") {
			// we're updating the user's location.
			$where = array();
			foreach (array("lat", "lon", "q", "place_id") as $k) {
				if (!empty($_POST[$k])) $where[$k] = $_POST[$k];
			}
			switch (@$_POST['submit']) {
			case 'Move!':
				$r = $fe->update($where); // equivalent to $fe->call("update", $where)
				header("Location: ".$_SERVER['SCRIPT_NAME']);
				exit;
			case 'Lookup':
				echo "<p>Lookup results:</p><div><code>".nl2br(htmlspecialchars(var_export($fe->lookup($where), TRUE)))."</code></div>";
				break;
			}
		}
		
		?><p>You are authenticated with <a href="<?php print htmlspecialchars(FireEagle::$FE_ROOT) ?>">Fire Eagle</a>!  (<a href="?f=start">Change settings</a>.)</p><?php
				   
		$loc = $fe->user(); // equivalent to $fe->call("user")
		?><h2>Where you are<?php if ($loc->user->best_guess) echo ": ".htmlspecialchars($loc->user->best_guess->name) ?></h2><?php
		if (empty($loc->user->location_hierarchy)) {
			?><p>Fire Eagle doesn't know where you are yet.</p><?php // '
		} else {
			foreach ($loc->user->location_hierarchy as $location) {
				switch ($location->geotype) {
				case 'point':
					$locinfo = "[".$location->latitude.", ".$location->longitude."]";
					break;
				case 'box':
					$locinfo = "[[".$location->bbox[0][1].", ".$location->bbox[0][0]."], ["
						.$location->bbox[1][1].", ".$location->bbox[1][0]."]]";
					break;
				default:
					$locinfo = "[unknown]";
					break;
				}
				if ($location->best_guess) $locinfo .= " BEST GUESS";
				print "<h3>".htmlspecialchars($location->level_name).": ".htmlspecialchars($location->name)." $locinfo</h3>";
				print "<ul>";
				// turn location object into array, with sorted keys
				$l = array(); foreach ($location as $k => $v) $l[$k] = $v; ksort($l);
				foreach ($l as $k => $v) {
					print "<li>".htmlspecialchars($k).": <b>".htmlspecialchars(var_export($v, TRUE))."</b></li>";
				}
				print "</ul>";
			}
		}
		
		if (TRUE || $_SESSION['can_write']) { // fix when we get 'writable' from the 'user' response

			?><h2>Update</h2><p>Enter a location below and click "Move!" to update.</p>

			<form method="POST">
			<p><label for="free-text-entry">Free-text entry:</label> <input type="text" name="q" id="free-text-entry" size="40"></p>
			<p><label for="place-id">Place ID:</label> <input type="text" name="place_id" id="place-id" size="40"></p>
			<p><label for="lat">Lat:</label> <input type="text" name="lat" id="lat" size="10"> <label for="lon">Lon:</label> <input type="text" name="lon" size="10"></p>
			<input type="submit" name="submit" value="Move!">
			or just check your query: <input type="submit" name="submit" value="Lookup">
			</form><?php

		}
		// END step 3
	} else {
		// not authenticated yet, so give a link to use to start authentication.
		?><p><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?f=start">Click here to authenticate with Fire Eagle!</a></p><?php
	}
}

main();

?>
