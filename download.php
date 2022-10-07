<?php

use Abraham\TwitterOAuth\Request;
use Chrisyue\PhpM3u8\Stream\TextStream;
use Chrisyue\PhpM3u8\Facade\ParserFacade;


require __DIR__ . '/vendor/autoload.php';


class TwitterDownloader
{

    function __construct($video_url)
    {
        $this->m3u8_url     = '';
        $this->video_url    = $video_url;
        $this->video_url_id = str_replace('https://twitter.com/i/status/', '', $video_url);
        $this->headers      = array();
    }

    function get_bearer_token()
    {
        $response =  Requests::get('https://abs.twimg.com/web-video-player/TwitterVideoPlayerIframe.cefd459559024bfb.js');
        preg_match('/Bearer ([a-zA-Z0-9%-])+/', $response->body, $matches);
        $bearer_token = $matches[0];
        $this->headers['Authorization'] = $bearer_token;
    }

    function get_guest_token()
    {
        $response =  Requests::post('https://api.twitter.com/1.1/guest/activate.json', $this->headers);
        preg_match('/[0-9]+/', $response->body, $matches);
        $guest_token = $matches[0];
        $this->headers['x-guest-token'] = $guest_token;
    }

    function get_m3u8_url()
    {
        # Get video config
        $response =  Requests::get('https://api.twitter.com/1.1/videos/tweet/config/' . $this->video_url_id . '.json', $this->headers);
        $json = json_decode($response->body, true);

        # Get playback url
        $playback_url = $json['track']['playbackUrl'];
        $response =  Requests::get($playback_url);

        # Get m3u8 url
        $m3u8_parser = new ParserFacade();
        $m3u8_list = $m3u8_parser->parse(new TextStream($response->body));
        $this->m3u8_url = 'https://video.twimg.com' . end($m3u8_list['EXT-X-STREAM-INF'])['uri'];
    }

    function download($name)
    {
        $this->get_bearer_token();
        $this->get_guest_token();
        $this->get_m3u8_url();
        shell_exec('ffmpeg -i ' . $this->m3u8_url . ' -c copy  ./uploads/' . $name . '');
        // print_r($this->m3u8_url );
    }
}



if (isset($_POST['URL'])) {
    $video_url = $_POST['URL'];
    $twitter_downloader = new TwitterDownloader($video_url);
    $file = uniqid() . ".mp4";
    $twitter_downloader->download($file);
    if (file_exists("uploads/" . $file)) {
        echo "uploads/" . $file;
    } else {
        echo "failure";
    }
}
if (isset($_POST['unlink'])) {
    $video_url = $_POST['unlink'];
    unlink($video_url);
}
