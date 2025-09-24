# 970 Design Video Field

An Advanced Custom Fields (ACF) Field for Cloudflare Stream that allows you to upload videos to Cloudflare Stream and embed them on your site.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Advanced Custom Fields (ACF)
- Cloudflare Stream subscription

## Installation

1. Install plugin from WordPress directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add your Cloudflare credentials to the settings page
4. Use the new Cloudflare Stream field like any other ACF field

## Features

- Upload videos directly to Cloudflare Stream
- Get HLS and Dash manifests for streaming
- Generate thumbnails and preview URLs
- Configure basic video options (loop, autoplay, mute, controls)
- Integration with ACF fields

## Returned Data

The ACF field will return an array with the following data for you to use on your website frontend:
```php
Array
(
    [filename] => 'uploaded-video.mp4'
    [hls] => 'https://videodelivery.net/video-id/hls/playlist.m3u8'
    [dash] => 'https://videodelivery.net/video-id/dash/playlist.mpd'
    [thumbnail] => 'https://videodelivery.net/unique-video-uid/thumbnails/thumbnail.jpg'
    [preview] => 'https://watch.videodelivery.net/video-id'
    [muted] => '1'
    [autoplay] => '1'
    [loop] => '0'
    [controls] => '0'
)
```

## Security

Your Cloudflare credentials are encrypted and only saved locally on your WordPress installation, and are not transferred or shared with any external services.

## Gotchas

If You are getting the error "Error fetching videos from Cloudflare" or "Failed connecting to Cloudflare stream" - and you see in the console it's a CORS error - it's likely that your Cloudflare credentials are incorrect, your API credentials' permissions are incorrect, or you do not have Cloudflare Stream enabled on your account.  Double-check your credentials & permissions and ensure you have Stream enabled and enough space in your account.

## Vue Integration

If you're using Vue.js in your project, you can use our companion component to render the data provided by this field:
[@970design/vue-video-stream](https://www.npmjs.com/package/@970design/vue-video-stream)

## Related Services

- [Advanced Custom Fields](https://www.advancedcustomfields.com/)
- [Cloudflare Stream](https://www.cloudflare.com/products/cloudflare-stream/)

## License

GPLv2 or later - [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)

## Credits

The development of this package is sponsored by [970 Design](https://970design.com), a creative agency based in Vail, Colorado.  If you need help with your headless WordPress project, please don't hesitate to [reach out](https://970design.com/reach-out/).