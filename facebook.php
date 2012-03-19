<?php

require_once('AppInfo.php');

// Enforce https on production
if (substr(AppInfo::getUrl(), 0, 8) != 'https://' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
  header('Location: https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}

require_once('utils.php');
require_once('sdk/src/facebook.php');

$facebook = new Facebook(array(
  'appId'  => AppInfo::appID(),
  'secret' => AppInfo::appSecret(),
));

$user_id = $facebook->getUser();
if ($user_id) {
  try {
    // Fetch the viewer's basic information
    $basic = $facebook->api('/me');
  } catch (FacebookApiException $e) {
    // If the call fails we check if we still have a user. The user will be
    // cleared if the error is because of an invalid accesstoken
    if (!$facebook->getUser()) {
      header('Location: '. AppInfo::getUrl($_SERVER['REQUEST_URI']));
      exit();
    }
  }

  $app_using_friends = $facebook->api(array(
    'method' => 'fql.query',
    'query' => 'SELECT uid, name FROM user WHERE uid IN(SELECT uid2 FROM friend WHERE uid1 = me()) AND is_app_user = 1'
  ));
}

$app_info = $facebook->api('/'. AppInfo::appID());
$app_name = idx($app_info, 'name', '');

?>
<!DOCTYPE html>
<html xmlns:fb="http://ogp.me/ns/fb#" lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>projectile » Open Source HTML5 space shooter</title>
    <meta name="description" content="Play this old-school action space shooter right in your browser and see how much you can score!" />
    <meta property="og:title" content="<?php echo he($app_name); ?>" />
    <meta property="og:type" content="game" />
    <meta property="og:url" content="<?php echo AppInfo::getUrl(); ?>" />
    <meta property="og:image" content="<?php echo AppInfo::getUrl('/assets/images/logo75x75.png'); ?>" />
    <meta property="og:site_name" content="<?php echo he($app_name); ?>" />
    <meta property="og:description" content="Play this old-school action space shooter right in your browser and see how much you can score!" />
    <meta property="fb:app_id" content="<?php echo AppInfo::appID(); ?>" />
    <style type="text/css">
      html, body {
        padding: 0;
        margin: 0;
        border: none;
        width: 100%;
      }
      body {
        background-color: #000;
        background-image: url('assets/images/background.png');
        background-repeat: no-repeat;
        background-size: 100%;
      }
      #login {
        width: 300px;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid black;
        padding: 32px;
        margin: 0 auto;
        margin-top: 200px;
        font-family: "Courier New", Courier, sans-serif;
        font-weight: bold;
        font-size: 12pt;
        color: #99ff00;
        text-align: center;
      }
      div.fb-login-button {
        width: 200px;
        margin: 0 auto;
        margin-top: 8px;
        margin-bottom: 8px;
      }
      #loadscreen, #gameoverscreen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
      }
      #gameoverscreen {
        display: none;
      }
      #loadscreen-inner {
        width: 740px;
        margin: 0 auto;
        margin-top: 300px;
        font-family: "Courier New", Courier, sans-serif;
        font-size: 12pt;
        font-weight: bolder;
        color: #99ff00;
        text-align: center;
      }
      #loadbar {
        display: inline-block;
        background-color: #0f0;
        width: 0;
      }
      #startlink {
        display: none;
        color: #fff;
      }
      #worldbox {
        width: 740px;
        height: 640px;
        margin: 0 auto;
        padding: 0;
        border: none;
        background-color: black;
      }
      #help {
        width: 740px;
        margin: 0 auto;
        text-align: center;
        font-family: "Courier New", Courier, sans-serif;
        font-weight: bold;
        color: #4c8000;
      }
      #webglnote {
        color: #264000;
      }
      #stats {
        width: 740px;
        margin: 0 auto;
        margin-top: 40px;
        font-family: "Courier New", Courier, sans-serif;
        font-weight: bolder;
        font-size: 12pt;
        text-align: center;
        color: #99ff00;
      }
      #gameoverscreeninner {
        margin: 0 auto;
        margin-top: 100px;
        padding: 32px;
        border-radius: 16px;
        background: rgba(0, 0, 0, 0.5);
        width: 300px;
        font-family: "Courier New", Courier, sans-serif;
        font-weight: bold;
        font-size: 12pt;
        color: #99ff00;
        text-align: center;
      }
      #gameoverscore {
        color: #fff;
        font-weight: bolder;
      }
      #playagainlink {
        color: #fff;
      }
    </style>
  </head>
  <body>
    <div id="fb-root"></div>


    <?php if (isset($basic)) { ?>

      <div id="loadscreen">
        <div id="loadscreen-inner">
          <span id="loadnote">Loading, please wait...</span>
          <br/>
          <span id="loadbar">&nbsp;</span>
          <br />
          <br />
          <a id="startlink" href="#">START GAME</a>
        </div>
      </div>

      <div id="stats">
        Welcome, <?php echo he(idx($basic, 'name')); ?> -
        Time: <span id="time">60</span> seconds -
        Score: <span id="score">0</span>
      </div>

      <div id="worldbox">
        <canvas id="world" width="740" height="640">
          This browser can not run this game (canvas support missing).
        </canvas>
      </div>

      <div id="help">
        Use arrow keys to move, space to shoot.
        <span id="webglnote"></span>
      </div>

      <canvas id="bufferWorld" width="740" height="7560" style="display: none;">
        This browser can not run this game (canvas support missing).
      </canvas>

      <div id="gameoverscreen">
        <div id="gameoverscreeninner">
          Congratulations, you scored <span id="gameoverscore"></span> points.
          <br />
          <br />
          Share this game with your friends and see how they score!
          <br />
          <br />
          <a id="playagainlink" href="./">Click to play again</a>
        </div>
      </div>

      <script data-main="./scripts/app/facebook" src="./scripts/vendor/require-jquery.min.js"></script>

    <?php } else { ?>

      <div id="login">
        Please
        <div class="fb-login-button" data-scope=""></div>
        to play the game.
      </div>

    <?php } ?>


    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-2127388-10']);
      _gaq.push(['_trackPageview']);

      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>

    <script type="text/javascript">
      window.fbAsyncInit = function() {
        FB.init({
          appId      : '<?php echo AppInfo::appID(); ?>', // App ID
          channelUrl : '//<?php echo $_SERVER["HTTP_HOST"]; ?>/channel.html', // Channel File
          status     : true, // check login status
          cookie     : true, // enable cookies to allow the server to access the session
          xfbml      : true // parse XFBML
        });

        // Listen to the auth.login which will be called when the user logs in
        // using the Login button
        FB.Event.subscribe('auth.login', function(response) {
          // We want to reload the page now so PHP can read the cookie that the
          // Javascript SDK sat. But we don't want to use
          // window.location.reload() because if this is in a canvas there was a
          // post made to this page and a reload will trigger a message to the
          // user asking if they want to send data again.
          window.location = window.location;
        });

        FB.Canvas.setAutoGrow();
      };

      // Load the SDK Asynchronously
      (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/all.js";
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));
    </script>
  </body>
</html>