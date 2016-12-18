<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/25/15
 * Time: 13:13
 */
namespace jct;

use TwitterOAuth\Auth\SingleUserAuth;
/**
 * Serializer Namespace
 */
use TwitterOAuth\Serializer\ArraySerializer;


// Return cached tweets if we have them, doesn't call api more than once per minute
$from_transient = get_site_transient('twitter_context');
if($from_transient) {
    return array_merge($from_transient, ['from' => 'transient']);
}

// ********* SET ALL CREDENTIALS AND USER INFO UP HERE!!! *********

/**
 * Array with the OAuth tokens provided by Twitter
 *   - consumer_key        Twitter API key
 *   - consumer_secret     Twitter API secret
 *   - oauth_token         Twitter Access token         * Optional For GET Calls
 *   - oauth_token_secret  Twitter Access token secret  * Optional For GET Calls
 */
$credentials = [
    'consumer_key'    => Util::get_theme_option('twitter_consumer_key'),
    'consumer_secret' => Util::get_theme_option('twitter_consumer_secret'),
    //'oauth_token' => '',
    //'oauth_token_secret' => '',
];
$user = Util::get_theme_option('twitter_handle');


/**
 * Instantiate SingleUser
 *
 * For different output formats you can set one of available serializers
 * (Array, Json, Object, Text or a custom one)
 */
try {
    $auth = new SingleUserAuth($credentials, new ArraySerializer());
} catch(\Exception $e) {
    return 'Message: ' . $e->getMessage();
}
/**
 * Returns a collection of the most recent Tweets posted by the user
 * https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
 */
$params = [
    'screen_name'     => $user,
    'count'           => 1,
    'exclude_replies' => true,
];
/**
 * Send a GET call with set parameters
 */
try {
    date_default_timezone_set('GMT');
    $response = $auth->get('statuses/user_timeline', $params);
} catch(\Exception $e) {
    return 'Message: ' . $e->getMessage();
}

// I got this from the internet, but sadly credit is lost
function ago($tm, $rcs = 0) {
    $cur_tm = time();
    $dif = $cur_tm - $tm;
    $pds = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year', 'decade'];
    $lngh = [1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600];
    for($v = sizeof($lngh) - 1; ($v >= 0) && (($no = $dif / $lngh[$v]) <= 1); $v--) {
        ;
    }
    if($v < 0) {
        $v = 0;
    }
    $_tm = $cur_tm - ($dif % $lngh[$v]);
    $no = floor($no);
    if($no <> 1) {
        $pds[$v] .= 's';
    }
    $x = sprintf("%d %s ", $no, $pds[$v]);
    if(($rcs == 1) && ($v >= 1) && (($cur_tm - $_tm) > 0)) {
        $x .= time_ago($_tm);
    }
    return $x;
}

function links($text, $urls) {
    foreach($urls as $url) {
        $display_url = $url['display_url'];
        $expanded_url = $url['expanded_url'];
        $text = str_replace($url['url'], "<a href='$expanded_url' target='_blank'>$display_url</a>", $text);
    }
    return $text;
}

function hash_and_at($text) {
    $patterns = [
        '#(https?\://t.co[^\s]*)#i',
        '/@([a-z0-9_]+)/i',
        '/#([a-z0-9_]+)/i',
    ];
    $replace = [
        '<a href="$1" target="_blank">$1</a>',
        '<a href="http://twitter.com/$1" target=_blank">@$1</a>',
        '<a href="http://twitter.com/search?q=%23$1" target=_blank">#$1</a>',
    ];
    $text = preg_replace($patterns, $replace, $text); // Parse links
    return $text;
}

function retweet_text($raw) {
    if($raw['retweeted_status']) {
        $text = "RT @" . $raw['retweeted_status']['user']['screen_name'] . ": " . $raw['retweeted_status']['text'];
        return $text;
    } else {
        return $raw['text'];
    }
}

$tweets = [];
foreach($response as $raw_tweet) {
    $text = retweet_text($raw_tweet);
    $text = links($text, $raw_tweet['entities']['urls']);
    $text = hash_and_at($text);
    $ago = ago(strtotime($raw_tweet['created_at']));
    $url = "http://twitter.com/$user/status/" . $raw_tweet['id'];
    $tweet = ['text' => $text, 'ago' => $ago, 'url' => $url, 'raw' => $raw_tweet];
    $tweets[] = $tweet;
}
$twitter = ['handle' => $user, 'url' => "http://twitter.com/$user", 'tweets' => $tweets];
set_site_transient('twitter_context', $twitter, 60);

return array_merge($twitter, ['from' => 'api']);