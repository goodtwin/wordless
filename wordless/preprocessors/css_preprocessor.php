<?php

require_once "wordless_preprocessor.php";

/**
 * Compile Sass files using the `compass` executable.
 *
 * CompassPreprocessor relies on some preferences to work:
 * - compass.compass_path (defaults to "/usr/bin/compass"): the path to the Compass executable
 * - compass.output_style (defaults to "compressed"): the output style used to render css files
 *   (check Compass documentation for more details: http://compass-style.org/help/tutorials/configuration-reference/)
 *
 * You can specify different values for this preferences using the Wordless::set_preference() method.
 *
 * @copyright welaika &copy; 2011 - MIT License
 * @see WordlessPreprocessor
 */
class CSSPreprocessor extends WordlessPreprocessor {

  public function __construct() {
    parent::__construct();

    $this->mark_preference_as_deprecated("compass.compass_path", "css.compass_path");
    $this->mark_preference_as_deprecated("compass.output_style", "css.output_style");

    $this->set_preference_default_value("css.compass_path", "/usr/bin/compass");
    $this->set_preference_default_value("css.output_style", "compressed");

    $this->set_preference_default_value("css.require_libs", array());
  }

  /**
   * Overrides WordlessPreprocessor::asset_hash()
   * @attention This is raw code. Right now all we do is find all the *.{sass,scss} files, concat
   * them togheter and generate an hash. We should find exacty the sass files required by
   * $file_path asset file.
   */
  protected function asset_hash($file_path) {
    $hash = array(parent::asset_hash($file_path));
    $base_path = dirname($file_path);
    $files = Wordless::recursive_glob(dirname($base_path), '*.{sass,scss}', GLOB_BRACE);
    sort($files);
    $hash_seed = array();
    foreach ($files as $file) {
      date_default_timezone_set('America/Chicago');
      $hash_seed[] = $file . date("%U", filemtime($file));
    }
    // Concat original file onto hash seed for uniqueness so each file is unique
    $hash_seed[] = $file_path;
    return md5(join($hash_seed));
  }

  /**
   * Overrides WordlessPreprocessor::comment_line()
   */
  protected function comment_line($line) {
    return "/* $line */\n";
  }

  /**
   * Overrides WordlessPreprocessor::content_type()
   */
  protected function content_type() {
    return "text/css";
  }

  /**
   * Overrides WordlessPreprocessor::error()
   */
  protected function error($description) {
    $error = "";
    $error = $error . "/************************\n";
    $error = $error . $description;
    $error = $error . "************************/\n\n";
    $error = $error . sprintf(
      'body::before { content: "%s"; font-family: monospace; white-space: pre; display: block; background: #eee; padding: 20px; }',
      'Damn, we\'re having problems compiling the Sass. Check the CSS source code for more infos!'
    );
    return $error;
  }

  /**
   * Process a file, executing Compass executable.
   *
   * Execute the Compass executable, overriding the no-op function inside
   * WordlessPreprocessor.
   */
  protected function process_file($file_path, $temp_path) {
    $this->validate_executable_or_throw($this->preference("css.compass_path"));

    // On cache miss, we build the file from scratch
    $pb = new ProcessBuilder(array(
      $this->preference("css.compass_path"),
      Wordless::join_paths(dirname(__FILE__), "css_preprocessor.rb"),
      "compile"
    ));

    // Fix for MAMP environments, see http://goo.gl/S5KFe for details
    $pb->setEnv("DYLD_LIBRARY_PATH", "");

    $pb->add($file_path);

    $pb->add("--paths");
    $pb->add(Wordless::theme_stylesheets_path());
    #$pb->add(Wordless::theme_javascripts_path());

    if ($this->preference("css.yui_compress")) {
      $pb->add("--compress");
    }

    if ($this->preference("css.yui_munge")) {
      $pb->add("--munge");
    }

    $proc = $pb->getProcess();
    $code = $proc->run();

    if ($code != 0) {
      throw new WordlessCompileException(
        "Failed to run the following command: " . $proc->getCommandLine(),
        $proc->getErrorOutput()
      );
    }

    return $proc->getOutput();
  }

  /**
   * Overrides WordlessPreprocessor::supported_extensions()
   */
  public function supported_extensions() {
    return array("sass", "scss");
  }


  /**
   * Overrides WordlessPreprocessor::to_extension()
   */
  public function to_extension() {
    return "css";
  }

}

