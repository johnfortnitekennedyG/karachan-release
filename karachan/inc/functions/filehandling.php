<?php

// Karachan's better image handling
function karachan_handle_image($input, $extension) {
	global $config;

	// check input exists first
	if(!file_exists($input) || !is_readable($input)) { error("can't read input image"); }
	$temp_file = 'tmp/cache/processing.jpg';

	// already processed? skip it
	$width = 0;
	$height = 0;
	$thumb_width = 0;
	$thumb_height = 0;
	try {
		if($extension == 'gif') {
			// get new hash
			$hash = md5_file($input);
			if($hash === false) { error("failed to hash image"); }
			$destination = 'stnk/' . $hash . '.gif';

			// just move the file lmao
			if(!@move_uploaded_file($input, $destination)) { error($config['error']['nomove']); }
		} else {
			$img = new Imagick($input);
			
			// strip EXIF/meta, no shady business
			$img->stripImage();

			// resize if it's bigger than max allowed
			if($img->getImageWidth() > $config['max_image_size'] || $img->getImageHeight() > $config['max_image_size']) {
				$img->resizeImage($config['max_image_size'], $config['max_image_size'], Imagick::FILTER_LANCZOS, 1, true);
			}
			$width = $img->getImageWidth();
			$height = $img->getImageHeight();

			// convert to jpeg and save full image
			$img->setImageFormat('jpeg');
			$img->setImageCompression(Imagick::COMPRESSION_JPEG);
			$img->setImageCompressionQuality(80);
			$img->writeImage($temp_file);

			// oh, destination wasnt saved properly
			if(!file_exists($temp_file)) { error("processed image was not saved properly"); }

			// get new hash
			$hash = md5_file($temp_file);
			if($hash === false) { error("failed to hash image"); }
			$destination = 'stnk/' . $hash . '.jpg';

			// we just need to get the image somehow
			if(!file_exists($destination)) { $img->writeImage($destination); }
			if(!file_exists($destination)) { error("processed image was not saved properly to destination"); }
			$img->destroy();
			unlink($temp_file);
		}

		// make thumbnail
		$thumbnail = 'krth/' . $hash . '.jpg';
		if(!file_exists($thumbnail)) {
			$thumb = new Imagick($destination);
			$thumb->thumbnailImage($config['thumb_width'], $config['thumb_height'], true);
			$thumb->setImageCompressionQuality(40);
			$thumb->writeImage($thumbnail);
		} else {
			$thumb = new Imagick($thumbnail);
		}
		$thumb_width = $thumb->getImageWidth();
		$thumb_height = $thumb->getImageHeight();
		$thumb->destroy();
	} catch(Exception $e) {
		error("image processing failed: " . $e->getMessage());
	}

	return [
		'hash' => $hash,
		'width' => $width,
		'height' => $height,
		'thumb_width' => $thumb_width,
		'thumb_height' => $thumb_height
	];
}

// Image OCR
function ocr_image(array $config, string $img_path): string {
	// The default preprocess command is an ImageMagick b/w quantization.
	$ret = shell_exec_error(sprintf('convert -monochrome %s -', escapeshellarg($img_path)) . ' | tesseract stdin stdout 2>/dev/null');
	if ($ret === false) {
		throw new RuntimeException('Unable to run tesseract');
	}
	return trim($ret);
}

// undoes a image from a post, basically nuking it.
function undoImage(array $post) {
	if (!$post['has_file'] || !isset($post['files'])) { return; }
	foreach ($post['files'] as $key => $file) {
		if (isset($file['file_path'])) { file_unlink($file['file_path']); }
		if (isset($file['thumb_path'])) { file_unlink($file['thumb_path']); }
	}
}