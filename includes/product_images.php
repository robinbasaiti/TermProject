<?php

function marketplace_prepare_product_image_uploads(array $files, int $limit = 4): array
{
    if ($limit < 1 || empty($files['tmp_name']) || !is_array($files['tmp_name'])) {
        return [];
    }

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $max_size = 5 * 1024 * 1024;
    $uploads = [];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        throw new RuntimeException('Unable to validate uploaded images.');
    }

    try {
        foreach ($files['tmp_name'] as $index => $tmp_name) {
            if (count($uploads) >= $limit) {
                break;
            }

            $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            $size = (int)($files['size'][$index] ?? 0);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_name) || $size < 1 || $size > $max_size) {
                continue;
            }

            $mime = finfo_file($finfo, $tmp_name);
            if (!isset($allowed_types[$mime])) {
                continue;
            }

            $uploads[] = [
                'tmp_name' => $tmp_name,
                'filename' => uniqid('img_', true) . '.' . $allowed_types[$mime],
            ];
        }
    } finally {
        finfo_close($finfo);
    }

    return $uploads;
}

function marketplace_store_product_images(mysqli $conn, int $product_id, array $uploads, string $upload_dir): array
{
    if (!$uploads) {
        return [];
    }

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('Unable to create the upload directory.');
    }

    $moved_paths = [];

    try {
        foreach ($uploads as $upload) {
            $destination = rtrim($upload_dir, '\\/') . DIRECTORY_SEPARATOR . $upload['filename'];
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('Unable to store an uploaded image.');
            }

            $moved_paths[] = $destination;

            $stmt = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'is', $product_id, $upload['filename']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (Throwable $e) {
        marketplace_delete_files($moved_paths);
        throw $e;
    }

    return $moved_paths;
}

function marketplace_delete_files(array $paths): void
{
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            unlink($path);
        }
    }
}

function marketplace_resolve_uploaded_image_path(string $upload_dir, string $image_url): string
{
    return rtrim($upload_dir, '\\/') . DIRECTORY_SEPARATOR . basename($image_url);
}
