<?php
namespace Melodic\Web
{
	use \Melodic\Config;

	class Request
	{
		public string $method = "";
		public string $vanity = "";
		public array $query = array();
		public array $request = array();
		public string $controller = "";
		public string $action = "";
		public array $params = array();
		public array $headers = array();
		public bool $isApiRequest = false;
		public string $apiRoot = "";

		public function __construct()
		{
			$this->controller = Config::Get("defaultController") ?? "Home";
			$this->action = Config::Get("defaultAction") ?? "Index";

			$this->apiRoot = strtolower(Config::Get("apiRoot")) ?? "/api";

			$this->method = strtolower($_SERVER["REQUEST_METHOD"]);

			parse_str($_SERVER["QUERY_STRING"], $this->query);

			if (isset($_SERVER["REQUEST_URI"])) {
				$this->vanity = strtolower($_SERVER["REQUEST_URI"]);
			}

			if (str_starts_with($this->vanity, $this->apiRoot)) {
				$this->vanity = str_replace(strtolower(Config::Get("apiRoot")), "", $this->vanity);
				$this->isApiRequest = true;
			}

			$this->request = explode("/", substr($this->vanity, 1), 3);

			if (!strlen($this->request[(count($this->request) - 1)]) > 0) {
				array_pop($this->request);
			}

			if (isset($this->request[0])) {
				$this->controller = $this->request[0];
			}
			
			if (isset($this->request[1])) {
				$this->action = $this->request[1];
			}

			if (isset($this->request[2])) {
				$this->params = explode("/", $this->request[2]);
			}

			$this->headers = \getHeaders();
		}
	}
}
?>