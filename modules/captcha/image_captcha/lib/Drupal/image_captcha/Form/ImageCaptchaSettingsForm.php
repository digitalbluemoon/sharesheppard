<?php

/**
 * @file
 * Contains \Drupal\image_captcha\Form\ImageCaptchaSettingsForm.
 */

namespace Drupal\image_captcha\Form;

use Drupal\Core\ControllerInterface;
use Drupal\system\SystemConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\Language;

/**
 * Displays the pants settings form.
 */
class ImageCaptchaSettingsForm extends SystemConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'image_captcha_settings';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
  // Add CSS and JS for theming and added usability on admin form.
  drupal_add_library('image_captcha','imagecaptcha.library');

  // First some error checking.
  $setup_status = _image_captcha_check_setup(FALSE);
  if ($setup_status & IMAGE_CAPTCHA_ERROR_NO_GDLIB) {
    drupal_set_message(t(
      'The Image CAPTCHA module can not generate images because your PHP setup does not support it (no <a href="!gdlib" target="_blank">GD library</a> with JPEG support).',
      array('!gdlib' => 'http://php.net/manual/en/book.image.php')
    ), 'error');
    // It is no use to continue building the rest of the settings form.
    return $form;
  }

  $form['image_captcha_example'] = array(
    '#type' => 'details',
    '#title' => t('Example'),
    '#description' => t('Presolved image CAPTCHA example, generated with the current settings.'),
  );
  $form['image_captcha_example']['image'] = array(
    '#type' => 'captcha',
    '#captcha_type' => 'image_captcha/Image',
    '#captcha_admin_mode' => TRUE,
  );

  // General code settings.
  $form['image_captcha_code_settings'] = array(
    '#type' => 'details',
    '#title' => t('Code settings'),
  );
  $form['image_captcha_code_settings']['image_captcha_image_allowed_chars'] = array(
    '#type' => 'textfield',
    '#title' => t('Characters to use in the code'),
    '#default_value' => variable_get('image_captcha_image_allowed_chars', IMAGE_CAPTCHA_ALLOWED_CHARACTERS),
  );
  $form['image_captcha_code_settings']['image_captcha_code_length'] = array(
    '#type' => 'select',
    '#title' => t('Code length'),
    '#options' => array(2 => 2, 3, 4, 5, 6, 7, 8, 9, 10),
    '#default_value' => (int) variable_get('image_captcha_code_length', 5),
    '#description' => t('The code length influences the size of the image. Note that larger values make the image generation more CPU intensive.'),
  );
  // RTL support option (only show this option when there are RTL languages).
  $languages = language_list('direction');
  if (isset($languages[Language::DIRECTION_RTL])) {
    $form['image_captcha_code_settings']['image_captcha_rtl_support'] = array(
      '#title' => t('RTL support'),
      '#type' => 'checkbox',
      '#default_value' => variable_get('image_captcha_rtl_support', 0),
      '#description' => t('Enable this option to render the code from right to left for right to left languages.'),
    );
  }

  // Font related stuff.
  $form['image_captcha_font_settings'] = self::image_captcha_settings_font_section();

    // Color and file format settings.
  $form['image_captcha_color_settings'] = array(
    '#type' => 'details',
    '#title' => t('Color and image settings'),
    '#description' => t('Configuration of the background, text colors and file format of the image CAPTCHA.'),
  );
  $form['image_captcha_color_settings']['image_captcha_background_color'] = array(
    '#type' => 'textfield',
    '#title' => t('Background color'),
    '#description' => t('Enter the hexadecimal code for the background color (e.g. #FFF or #FFCE90). When using the PNG file format with transparent background, it is recommended to set this close to the underlying background color.'),
    '#default_value' => variable_get('image_captcha_background_color', '#ffffff'),
    '#maxlength' => 7,
    '#size' => 8,
  );
  $form['image_captcha_color_settings']['image_captcha_foreground_color'] = array(
    '#type' => 'textfield',
    '#title' => t('Text color'),
    '#description' => t('Enter the hexadecimal code for the text color (e.g. #000 or #004283).'),
    '#default_value' => variable_get('image_captcha_foreground_color', '#000000'),
    '#maxlength' => 7,
    '#size' => 8,
  );
  $form['image_captcha_color_settings']['image_captcha_foreground_color_randomness'] = array(
    '#type' => 'select',
    '#title' => t('Additional variation of text color'),
    '#options' => array(
      0 => t('No variation'),
      50 => t('Little variation'),
      100 => t('Medium variation'),
      150 => t('High variation'),
      200 => t('Very high variation'),
    ),
    '#default_value' => (int) variable_get('image_captcha_foreground_color_randomness', 100),
    '#description' => t('The different characters will have randomized colors in the specified range around the text color.'),
  );
  $form['image_captcha_color_settings']['image_captcha_file_format'] = array(
    '#type' => 'select',
    '#title' => t('File format'),
    '#description' => t('Select the file format for the image. JPEG usually results in smaller files, PNG allows tranparency.'),
    '#default_value' => variable_get('image_captcha_file_format', IMAGE_CAPTCHA_FILE_FORMAT_JPG),
    '#options' => array(
      IMAGE_CAPTCHA_FILE_FORMAT_JPG => t('JPEG'),
      IMAGE_CAPTCHA_FILE_FORMAT_PNG => t('PNG'),
      IMAGE_CAPTCHA_FILE_FORMAT_TRANSPARENT_PNG => t('PNG with transparent background'),
    ),
  );

  // distortion and noise settings
  $form['image_captcha_distortion_and_noise'] = array(
    '#type' => 'details',
    '#title' => t('Distortion and noise'),
    '#description' => t('With these settings you can control the degree of obfuscation by distortion and added noise. Do not exaggerate the obfuscation and assure that the code in the image is reasonably readable. For example, do not combine high levels of distortion and noise.'),
  );
  // distortion
  $form['image_captcha_distortion_and_noise']['image_captcha_distortion_amplitude'] = array(
    '#type' => 'select',
    '#title' => t('Distortion level'),
    '#options' => array(
      0 => t('@level - no distortion', array('@level' => '0')),
      1 => t('@level - low', array('@level' => '1')),
      2 => '2',
      3 => '3',
      4 => '4',
      5 => t('@level - medium', array('@level' => '5')),
      6 => '6',
      7 => '7',
      8 => '8',
      9 => '9',
      10 => t('@level - high', array('@level' => '10')),
    ),
    '#default_value' => (int) variable_get('image_captcha_distortion_amplitude', 0),
    '#description' => t('Set the degree of wave distortion in the image.'),
  );
  $form['image_captcha_distortion_and_noise']['image_captcha_bilinear_interpolation'] = array(
    '#type' => 'checkbox',
    '#title' => t('Smooth distortion'),
    '#default_value' => variable_get('image_captcha_bilinear_interpolation', FALSE),
    '#description' => t('This option enables bilinear interpolation of the distortion which makes the image look smoother, but it is more CPU intensive.'),
  );
  // noise
  $form['image_captcha_distortion_and_noise']['image_captcha_dot_noise'] = array(
    '#type' => 'checkbox',
    '#title' => t('Add salt and pepper noise'),
    '#default_value' => variable_get('image_captcha_dot_noise', 0),
    '#description' => t('This option adds randomly colored point noise.'),
  );
  $form['image_captcha_distortion_and_noise']['image_captcha_line_noise'] = array(
    '#type' => 'checkbox',
    '#title' => t('Add line noise'),
    '#default_value' => variable_get('image_captcha_line_noise', 0),
    '#description' => t('This option enables lines randomly drawn on top of the text code.'),
  );
  $form['image_captcha_distortion_and_noise']['image_captcha_noise_level'] = array(
    '#type' => 'select',
    '#title' => t('Noise level'),
    '#options' => array(
      1 => '1 - ' . t('low'),
      2 => '2',
      3 => '3 - ' . t('medium'),
      4 => '4',
      5 => '5 - ' . t('high'),
      7 => '7',
      10 => '10 - ' . t('severe'),
    ),
    '#default_value' => (int) variable_get('image_captcha_noise_level', 5),
  );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface:validateForm()
   */
  public function validateForm(array &$form, array &$form_state) {

    // Check image_captcha_image_allowed_chars for spaces.
    if (preg_match('/\s/', $form_state['values']['image_captcha_image_allowed_chars'])) {
      form_set_error('image_captcha_image_allowed_chars', t('The list of characters to use should not contain spaces.'));
    }

    if (!isset($form['image_captcha_font_settings']['no_ttf_support'])) {
      // Check the selected fonts.
      // Filter the image_captcha fonts array to pick out the selected ones.
      $fonts = array_filter($form_state['values']['image_captcha_fonts']);
      if (count($fonts) < 1) {
        form_set_error('image_captcha_fonts', t('You need to select at least one font.'));
      }
      if ($form_state['values']['image_captcha_fonts']['BUILTIN']) {
        // With the built in font, only latin2 characters should be used.
        if (preg_match('/[^a-zA-Z0-9]/', $form_state['values']['image_captcha_image_allowed_chars'])) {
          form_set_error('image_captcha_image_allowed_chars', t('The built-in font only supports Latin2 characters. Only use "a" to "z" and numbers.'));
        }
      }
      list($readable_fonts, $problem_fonts) = _image_captcha_check_fonts($fonts);
      if (count($problem_fonts) > 0) {
        form_set_error('image_captcha_fonts', t('The following fonts are not readable: %fonts.', array('%fonts' => implode(', ', $problem_fonts))));
      }
    }

    // check color settings
    if (!preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $form_state['values']['image_captcha_background_color'])) {
      form_set_error('image_captcha_background_color', t('Background color is not a valid hexadecimal color value.'));
    }
    if (!preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $form_state['values']['image_captcha_foreground_color'])) {
      form_set_error('image_captcha_foreground_color', t('Text color is not a valid hexadecimal color value.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface:submitForm()
   *
   * @see book_remove_button_submit()
   */
  public function submitForm(array &$form, array &$form_state) {
    if (!isset($form['image_captcha_font_settings']['no_ttf_support'])) {
      // Filter the image_captcha fonts array to pick out the selected ones.
      $fonts = array_filter($form_state['values']['image_captcha_fonts']);
      variable_set('image_captcha_fonts', $fonts);
    }

    parent::SubmitForm($form, $form_state);
  }

  /**
 * Form elements for the font specific setting.
 *
 * This is refactored to a separate function to avoid poluting the
 * general form function image_captcha_settings_form with some
 * specific logic.
 *
 * @return $form, the font settings specific form elements.
 */
  public function image_captcha_settings_font_section() {
  // Put it all in a details element.
  $form = array(
    '#type' => 'details',
    '#title' => t('Font settings'),
  );

  // First check if there is TrueType support.
  $setup_status = _image_captcha_check_setup(FALSE);
  if ($setup_status & IMAGE_CAPTCHA_ERROR_NO_TTF_SUPPORT) {
    // Show a warning that there is no TrueType support
    $form['no_ttf_support'] = array(
      '#type' => 'item',
      '#title' => t('No TrueType support'),
      '#markup' => t('The Image CAPTCHA module can not use TrueType fonts because your PHP setup does not support it. You can only use a PHP built-in bitmap font of fixed size.'),
    );

  }
  else {

    // Build a list of  all available fonts.
    $available_fonts = array();

    // List of folders to search through for TrueType fonts.
    $fonts = self::image_captcha_get_available_fonts_from_directories();
    // Cache the list of previewable fonts. All the previews are done
    // in separate requests, and we don't want to rescan the filesystem
    // every time, so we cache the result.
    variable_set('image_captcha_fonts_preview_map_cache', $fonts);
    // Put these fonts with preview image in the list
    foreach ($fonts as $token => $font) {
      $img_src = check_url(url('admin/config/people/captcha/image_captcha/font_preview/' . $token));
      $title = t('Font preview of @font (@file)', array('@font' => $font->name, '@file' => $font->uri));
      $available_fonts[$font->uri] = '<img src="' . $img_src . '" alt="' . $title . '" title="' . $title . '" />';
    }

    // Append the PHP built-in font at the end.
    $img_src = check_url(url('admin/config/people/captcha/image_captcha/font_preview/BUILTIN'));
    $title = t('Preview of built-in font');
    $available_fonts['BUILTIN'] = t('PHP built-in font: !font_preview',
      array('!font_preview' => '<img src="' . $img_src . '" alt="' . $title . '" title="' . $title . '" />')
    );

    $default_fonts = _image_captcha_get_enabled_fonts();
    $form['image_captcha_fonts'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fonts'),
      '#default_value' => $default_fonts,
      '#description' => t('Select the fonts to use for the text in the image CAPTCHA. Apart from the provided defaults, you can also use your own TrueType fonts (filename extension .ttf) by putting them in %fonts_library_general or %fonts_library_specific.',
        array(
          '%fonts_library_general' => 'sites/all/libraries/fonts',
          '%fonts_library_specific' => conf_path() . '/libraries/fonts',
        )
      ),
      '#options' => $available_fonts,
      '#attributes' => array('class' => array('image_captcha_admin_fonts_selection')),
      '#process' => array('form_process_checkboxes'),
    );


    // Font size.
    $form['image_captcha_font_size'] = array(
      '#type' => 'select',
      '#title' => t('Font size'),
      '#options' => array(
        9 => '9 pt - ' . t('tiny'),
        12 => '12 pt - ' . t('small'),
        18 => '18 pt',
        24 => '24 pt - ' . t('normal'),
        30 => '30 pt',
        36 => '36 pt - ' . t('large'),
        48 => '48 pt',
        64 => '64 pt - ' . t('extra large'),
      ),
      '#default_value' => (int) variable_get('image_captcha_font_size', 30),
      '#description' => t('The font size influences the size of the image. Note that larger values make the image generation more CPU intensive.'),
    );

  }

  // Character spacing (available for both the TrueType fonts and the builtin font.
  $form['image_captcha_font_settings']['image_captcha_character_spacing'] = array(
    '#type' => 'select',
    '#title' => t('Character spacing'),
    '#description' => t('Define the average spacing between characters. Note that larger values make the image generation more CPU intensive.'),
    '#default_value' => variable_get('image_captcha_character_spacing', '1.2'),
    '#options' => array(
      '0.75' => t('tight'),
      '1' => t('normal'),
      '1.2' => t('wide'),
      '1.5' => t('extra wide'),
    ),
  );

  return $form;
}

/**
 * Helper function to get fonts from the given directories.
 *
 * @param $directories (optional) an array of directories
 *   to recursively search through, if not given, the default
 *   directories will be used.
 *
 * @return an array of fonts file objects (with fields 'name',
 *   'basename' and 'filename'), keyed on the md5 hash of the font
 *   path (to have an easy token that can be used in an url
 *   without en/decoding issues).
 */
public function image_captcha_get_available_fonts_from_directories($directories=NULL) {
  // If no fonts directories are given: use the default.
  if ($directories === NULL) {
    $directories = array(
      drupal_get_path('module', 'image_captcha') . '/fonts',
      'sites/all/libraries/fonts',
      conf_path() . '/libraries/fonts',
    );
  }
  // Collect the font information.
  $fonts = array();
  foreach ($directories as $directory) {
    foreach (file_scan_directory($directory, '/\.[tT][tT][fF]$/') as $filename => $font) {
      $fonts[md5($filename)] = $font;
    }
  }

  return $fonts;
}
}