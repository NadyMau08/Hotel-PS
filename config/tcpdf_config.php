<?php
// config/tcpdf_config.php
// Configuración personalizada para TCPDF

// Solo definir si no están ya definidas
if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
}

// Información de la empresa
if (!defined('PDF_CREATOR')) {
    define('PDF_CREATOR', 'Hotel Melaque Puesta del Sol');
}

if (!defined('PDF_AUTHOR')) {
    define('PDF_AUTHOR', 'Sistema de Reservas');
}

if (!defined('PDF_HEADER_TITLE')) {
    define('PDF_HEADER_TITLE', 'Hotel Melaque Puesta del Sol');
}

if (!defined('PDF_HEADER_STRING')) {
    define('PDF_HEADER_STRING', "Gómez Farias No. 24\nSan Patricio Melaque, Jalisco\nC.P. 48980");
}

// Configuración de página
if (!defined('PDF_PAGE_FORMAT')) {
    define('PDF_PAGE_FORMAT', 'A4');
}

if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
}

if (!defined('PDF_MARGIN_LEFT')) {
    define('PDF_MARGIN_LEFT', 10);
}

if (!defined('PDF_MARGIN_RIGHT')) {
    define('PDF_MARGIN_RIGHT', 10);
}

if (!defined('PDF_MARGIN_TOP')) {
    define('PDF_MARGIN_TOP', 30);
}

if (!defined('PDF_MARGIN_BOTTOM')) {
    define('PDF_MARGIN_BOTTOM', 25);
}

if (!defined('PDF_MARGIN_HEADER')) {
    define('PDF_MARGIN_HEADER', 5);
}

if (!defined('PDF_MARGIN_FOOTER')) {
    define('PDF_MARGIN_FOOTER', 10);
}

// Configuración de fuentes
if (!defined('PDF_FONT_NAME_MAIN')) {
    define('PDF_FONT_NAME_MAIN', 'helvetica');
}

if (!defined('PDF_FONT_SIZE_MAIN')) {
    define('PDF_FONT_SIZE_MAIN', 10);
}

if (!defined('PDF_FONT_NAME_DATA')) {
    define('PDF_FONT_NAME_DATA', 'helvetica');
}

if (!defined('PDF_FONT_SIZE_DATA')) {
    define('PDF_FONT_SIZE_DATA', 8);
}

// Configuración de imágenes
if (!defined('PDF_IMAGE_SCALE_RATIO')) {
    define('PDF_IMAGE_SCALE_RATIO', 1.25);
}

// Configuración de paths (ajustar según tu estructura)
if (!defined('K_PATH_MAIN')) {
    define('K_PATH_MAIN', dirname(__FILE__) . '/');
}

if (!defined('K_PATH_URL')) {
    define('K_PATH_URL', 'http://localhost/hotel/');
}

if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
}

if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', K_PATH_MAIN . 'cache/');
}

if (!defined('K_PATH_URL_CACHE')) {
    define('K_PATH_URL_CACHE', K_PATH_URL . 'cache/');
}

if (!defined('K_PATH_IMAGES')) {
    define('K_PATH_IMAGES', K_PATH_MAIN . 'images/');
}

if (!defined('K_PATH_BLANK')) {
    define('K_PATH_BLANK', K_PATH_MAIN . '_blank.png');
}

// Configuraciones adicionales
if (!defined('K_BLANK_IMAGE')) {
    define('K_BLANK_IMAGE', '_blank.png');
}

if (!defined('PDF_HEADER_LOGO')) {
    define('PDF_HEADER_LOGO', '');
}

if (!defined('PDF_HEADER_LOGO_WIDTH')) {
    define('PDF_HEADER_LOGO_WIDTH', 0);
}

if (!defined('PDF_UNIT')) {
    define('PDF_UNIT', 'mm');
}

// Configuración de idioma (opcional)
if (!isset($l)) {
    $l = array();
}

$l['a_meta_charset'] = 'UTF-8';
$l['a_meta_dir'] = 'ltr';
$l['a_meta_language'] = 'es';
$l['w_page'] = 'página';

// Solo para debugging - eliminar en producción
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log('TCPDF Config cargado correctamente');
}
?>