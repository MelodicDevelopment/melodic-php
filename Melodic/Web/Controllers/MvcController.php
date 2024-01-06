<?php
namespace Melodic\Web\Controllers
{
	use Melodic\Config;
	use Melodic\Web\Request;
	use Melodic\Web\Response;

	class MvcController extends Controller
	{
		public $layout = "";
		public $bodyContent = "";
		public $sections = array();
		public $viewBag = array();

		public function __construct(Request $request)
		{
			parent::__construct($request);

			$this->layout = Config::Get("views")."/shared/layout.phtml";

			$configViewBagValues = Config::Get("viewBag");
			if (is_array($configViewBagValues)){
				foreach ($configViewBagValues as $key => $value){
					$this->ViewBag($key, $value);
				}
			}

			return $this;
		}

		public function Exec(): Response
		{
			$action = $this->request->action;
			if (method_exists($this, $action)){
				$params = $this->request->params;
				foreach ($this->request->query as $key => $value) {
					$params[$key] = $value;
				}
				
				$result = call_user_func_array(array($this, $action), $params);

				return $this->Html($result);
			}

			return Controller::PageNotFound();
		}

		public function ViewBag($key, $value = null)
		{
			if (isset($this->viewBag[$key])){
				if ($value != null){
					$this->viewBag[$key] = $value;
					return $this;
				} else {
					return $this->viewBag[$key];
				}
			} else {
				if ($value != null){
					$this->viewBag[$key] = $value;
					return $this;
				} else {
					return null;
				}
			}
		}

		public function View(mixed $model = null): string
		{
			ob_start();
			include_once Config::Get("views")."/".$this->request->controller."/".$this->request->action.".phtml";
			$this->bodyContent = ob_get_contents();
			ob_end_clean();

			preg_match_all("/#section (.*?)\n(.*?)#end/s", $this->bodyContent, $matches);

			if (count($matches) == 3){
				foreach ($matches[1] as $key => $section){
					$this->addSection($section, $matches[2][$key]);

					$this->bodyContent = str_replace($matches[0][$key], "", $this->bodyContent);
				}
			}

			ob_start();
			include_once $this->layout;
			$pageContent = ob_get_contents();
			ob_end_clean();

			return $pageContent;
		}

		public function RenderBody()
		{
			print $this->bodyContent;
		}

		public function RenderSection($section)
		{
			if (isset($this->sections[$section])) {
				print $this->sections[$section];
			} else {
				print "";
			}
		}

		public function AddSection($section, $content)
		{
			$this->sections[$section] = $content;
		}

		public function ErrorPage()
		{
			ob_start();
			include_once Config::Get("views")."/shared/error.phtml";
			$this->bodyContent = ob_get_contents();
			ob_end_clean();

			ob_start();
			include_once $this->layout;
			$pageContent = ob_get_contents();
			ob_end_clean();

			return $pageContent;
		}

		static function Exception(\Exception $ex)
		{
			$headers = getHeaders();

			if (isset($headers["Content-type"]) && $headers["Content-type"] == "application/json"){
				http_response_code(500);

				MvcController::JsonException($ex);
			} else {
				$controller = new MvcController(new Request());
				$controller->viewBag["Exception"] = $ex;
				$controller->ErrorPage();
			}
		}

		static function Error($no, $str, $file, $line)
		{
			$types = array(
				1 => "E_ERROR",
				2 => "E_WARNING",
				4 => "E_PARSE",
				8 => "E_NOTICE",
				16 => "E_CORE_ERROR",
				32 => "E_CORE_WARNING",
				64 => "E_COMPILE_ERROR",
				128 => "E_COMPILE_WARNING",
				256 => "E_USER_ERROR",
				512 => "E_USER_WARNING",
				1024 => "E_USER_NOTICE",
				6143 => "E_ALL",
				2048 => "E_STRICT",
				4096 => "E_RECOVERABLE_ERROR"
			);

			MvcController::Exception(new \Exception($types[$no]." Caught: ".$str." in ".$file." on line ".$line));
		}
	}
}
?>