<?php
namespace
{
	spl_autoload_register("melodic_autoload");
	register_include_path($_SERVER["DOCUMENT_ROOT"]);
	
	/**
     * Auto load the given class
     * @param string $class - The class name being instantiated
     * @return void
     */
	function melodic_autoload($class) 
	{
		$class = strtolower($class);
		if (is_readable($class.".php")) {
			$fileName = $class.".php";
		} else {
			$fileName = str_replace("\\", DIRECTORY_SEPARATOR, str_replace("_", DIRECTORY_SEPARATOR, $class)).".php";
		}

		//echo $fileName."<br>";

		require_once $fileName;
	}

	/**
     * Add to the include path
     * @param string $path - The path to add to the include path
     * @return void
     */
	function register_include_path($path)
	{
		set_include_path(get_include_path().":".$path);
	}

	/**************** COMMON FUNCTIONS *********************/

	/**
	 * Kill the script and print_r whatever var is passed
	 * @param mixed $var - The object to dump to the screen
	 * @param boolean $backtrace - A boolean flag indicating whether to include a backtrace
	 * @return void
	 */
	function kill(mixed $data, $backtrace = false): void
	{
		$printMsg = function($data) {
			print "<pre>";
			print_r($data);
			print "</pre>";
		};

		$printMsg($data);

		if ($backtrace) {
			print "<hr>";
			$printMsg(debug_backtrace());
		}

		die;
	}

	/**
	 * Redirect the page
	 * @param string $url - The url to redirect to
	 * @return void
	 */
	function redirect(string $url = "/"): void
	{
		header("Location: ".$url);
		die;
	}

	/**
	 * Get the current page url
	 * @return string
	 */
	function get_current_url(): string
	{
		$url = "http".(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? "s" : "")."://";
		$url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		return $url;
	}

	/**
	 * Convert data to serialized base64 encoded string
	 * @param mixed $data - The data to serialized and base 64 encoded
	 * @return string - The data as a serialized base64 encoded string
	 */
	function serial64_encode(mixed $data): string
	{
		return base64_encode(serialize($data));
	}

	/**
	 * Convert from serialized base64 encoded string to data
	 * @param string $data - The serialized base 64 encoded string representing data
	 * @return mixed - The deserialized data
	 */
	function serial64_decode(string $data): mixed
	{
		return unserialize(base64_decode($data));
	}

	/**
	 * Create a completely unique id
	 * @param string $prefix - A string prefix
	 * @return string - A string id
	 */
	function new_id(string $prefix = null): string
	{
		return $prefix.md5(uniqid(rand(), true));
	}

	/**
	 * Get all headers and return key/value array
	 * @return array
	 */
	function getHeaders(): array
	{
		$headers = [];
		$keys = array_filter(array_keys($_SERVER), function($key){ return \str_starts_with($key, "HTTP_"); });
		foreach ($keys as $key) {
			$header = str_replace(" ", "-", ucwords(str_replace("_", " ", strtolower(substr($key, 5)))));
			$headers[$header] = $_SERVER[$key];
		}

		return $headers;
	}
}
?>