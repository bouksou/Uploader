<?php
use Cake\Routing\Router;

Router::plugin('Uploader', function($routes) {
	$routes->fallbacks();
});
