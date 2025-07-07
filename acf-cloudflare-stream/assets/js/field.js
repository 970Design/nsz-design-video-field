/**
 * Included when cloudflare_stream fields are rendered for editing by publishers.
 */
( function( $ ) {
	function initialize_field( $field ) {
		/**
		 * $field is a jQuery object wrapping field elements in the editor.
		 */

		$('.nsz-cloudflare-stream-browse-modal').on('click', function (e) {
			e.preventDefault();

			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');

			$.ajax({
				type: "GET",
				url: 'https://api.cloudflare.com/client/v4/accounts/'+nsz_cloudflare_stream.account_id+'/stream/',
				headers: {
					"Authorization": "Bearer "+nsz_cloudflare_stream.api_token,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				success: function (data) {

					if (data.result.length) {
						let video_list = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
						video_list.html('');
						data.result.forEach(function (video) {
							let list_item = $('<li class="nsz-cloudflare-stream-modal-item" data-video-id="'+video.uid+'"><img src="'+video.thumbnail+'" alt="'+video.meta.name+'"><div>File: '+video.meta.name+' <br />Duration: '+video.duration+'sec <br />Uploaded: '+video.uploaded+'</div></li>');
							list_item.on('click', function () {
								cfs_wrap.find('.data-hls').val(video.playback.hls);
								cfs_wrap.find('.data-dash').val(video.playback.dash);
								cfs_wrap.find('.data-thumbnail').val(video.thumbnail);
								cfs_wrap.find('.data-preview').val(video.preview);
								cfs_wrap.find('.cloudflare-video-details').show();
								cfs_wrap.find('.cloudflare-video-thumbnail-preview').attr('src', video.thumbnail);
								cfs_wrap.find('.wrap-upload-field').hide();
								cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', false);
							});
							video_list.append(list_item);
						});
					}

					cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', true);
				},
				error: function (data) {
					console.log(data);
					error_area.html('Error fetching existing videos from Cloudflare.').show();
				}
			});
		});


		$('.nsz-cloudflare-stream-clear-video').on('click', function (e) {
			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			cfs_wrap.find('.data-hls').val('');
			cfs_wrap.find('.data-dash').val('');
			cfs_wrap.find('.data-thumbnail').val('');
			cfs_wrap.find('.data-preview').val('');
			cfs_wrap.find('.cloudflare-video-details').hide();
			cfs_wrap.find('.cloudflare-video-thumbnail-preview').attr('src', '');
			cfs_wrap.find('.wrap-upload-field').show();
		});

		$('.nsz-cloudflare-stream-file').on('change', function (e) {

			let file = e.target.files[0];
			let video_id = null;
			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');

			let hls_input = cfs_wrap.find('.data-hls');
			let dash_input = cfs_wrap.find('.data-dash');
			let thumbnail_input = cfs_wrap.find('.data-thumbnail');
			let preview_input = cfs_wrap.find('.data-preview');
			let video_details = cfs_wrap.find('.cloudflare-video-details');
			let filename = cfs_wrap.find('.data-filename');
			let error_area = cfs_wrap.find('.cloudflare-stream-error');
			let success_area = cfs_wrap.find('.cloudflare-stream-success');
			let progress_wrap = cfs_wrap.find('.cloudflare-stream-progress-wrap');
			let progress_bar = cfs_wrap.find('.cloudflare-stream-progress-bar');
			let video_thumbnail = cfs_wrap.find('.cloudflare-video-thumbnail-preview');
			let video_upload = cfs_wrap.find('.wrap-upload-field');

			error_area.html('').hide();
			success_area.html('').hide();

			if (!file.type.includes('video')) {
				error_area.html('Please upload a video file.').show();
				return;
			}

			let upload = new tus.Upload(file, {
				endpoint: 'https://api.cloudflare.com/client/v4/accounts/'+nsz_cloudflare_stream.account_id+'/stream',
				//endpoint: '/wp-json/nsz-cloudflare-stream/url?size='+file.size,
				retryDelays: [0, 3000, 5000, 10000, 20000],
				metadata: {
					name: file.name,
					filetype: file.type,
				},
				headers: {
					"Authorization": "Bearer "+nsz_cloudflare_stream.api_token,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				uploadSize: file.size,
				chunkSize: 50 * 1024 * 1024,
				onError: function (error) {
					error_area.html('Failed connecting to Cloudflare stream: ' + error).show();
				},
				onProgress: function (bytesUploaded, bytesTotal) {
					var percentage = ((bytesUploaded / bytesTotal) * 100).toFixed(2);
					progress_wrap.show();
					progress_bar.val(percentage);
				},
				onAfterResponse: function (req, res) {
					//get video id from header
					video_id = res.getHeader("stream-media-id");
				},
				onSuccess: function () {

					progress_wrap.hide();

					if (video_id) {
						success_area.html('Video Uploaded!  Fetching media URLs..').show();

						$.ajax({
							type: "GET",
							url: 'https://api.cloudflare.com/client/v4/accounts/'+nsz_cloudflare_stream.account_id+'/stream/'+video_id,
							headers: {
								"Authorization": "Bearer "+nsz_cloudflare_stream.api_token,
								"X-Auth-Email": nsz_cloudflare_stream.account_email,
								"X-Auth-Key": nsz_cloudflare_stream.api_token
							},
							success: function (data) {
								hls_input.val(data.result.playback.hls);
								dash_input.val(data.result.playback.dash);
								thumbnail_input.val(data.result.thumbnail);
								preview_input.val(data.result.preview);
								video_details.show();
								filename.html(data.result.meta.name);
								video_thumbnail.attr('src', data.result.thumbnail)

								success_area.html('Upload complete!').show();
								video_upload.hide();
							},
							error: function (data) {
								console.log(data);
								error_area.html('Error fetching video URLs from Cloudflare.').show();
							}
						});
					}
					else {
						error_area.html('Video ID not found.').show();
					}
				},
			});

			// Check if there are any previous uploads to continue.
			upload.findPreviousUploads().then(function (previousUploads) {
				if (previousUploads.length) {
					upload.resumeFromPreviousUpload(previousUploads[0]);
				}

				// Start the upload
				upload.start();
			});
		});

	}

	if( typeof acf.add_action !== 'undefined' ) {
		/**
		 * Run initialize_field when existing fields of this type load,
		 * or when new fields are appended via repeaters or similar.
		 */
		acf.add_action( 'ready_field/type=cloudflare_stream', initialize_field );
		acf.add_action( 'append_field/type=cloudflare_stream', initialize_field );
	}
} )( jQuery );
