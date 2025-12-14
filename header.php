<?php
// Global head includes
$asset_path = "assets/";
if (!file_exists('assets/css/style.css') && file_exists('../assets/css/style.css')) {
    $asset_path = "../assets/";
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?php echo $asset_path; ?>img/logo.png" type="image/png">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/style.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/all.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/brands.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/chisel-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/duotone-light.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/duotone-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/duotone-thin.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/duotone.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/etch-solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/fontawesome.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/jelly-duo-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/jelly-fill-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/jelly-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/light.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/notdog-duo-solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/notdog-solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-duotone-light.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-duotone-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-duotone-solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-duotone-thin.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-light.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/sharp-thin.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/slab-press-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/slab-regular.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/solid.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/svg-with-js.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/svg.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/thin.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/thumbprint-light.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/utility-duo-semibold.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/utility-fill-semibold.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/utility-semibold.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/v4-font-face.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/v4-shims.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/v5-font-face.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>css/whiteboard-semibold.css">