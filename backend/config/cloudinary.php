<?php

return [
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'upload_folder' => env('CLOUDINARY_UPLOAD_FOLDER', 'bookstore/covers'),
    'ca_bundle' => env('CLOUDINARY_CA_BUNDLE'),
];
