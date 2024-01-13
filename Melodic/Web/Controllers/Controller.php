<?php
namespace Melodic\Web\Controllers
{
	use \Melodic\DependencyInjection\Interfaces\iInject;
	use \Melodic\Web\Request;
	use \Melodic\Web\Response;

	abstract class Controller implements iInject
	{
		use \Melodic\DependencyInjection\Traits\InjectionTrait;

		protected Request $request;

		public function __construct(Request $request)
		{
			$this->request = $request;
			return $this;
		}

		protected function Html(string $html): Response
		{
			$response = new Response();
			$response->SetContentType("text/html");
			$response->SetContent($html);

			return $response;
		}
		
		protected function Json($obj): Response
		{
			$response = new Response();
			$response->SetContentType("application/json");
			$response->SetContent(json_encode($obj));

			return $response;
		}

		protected function GetActionByRouteAttribute(bool $httpMethodCheck = false): string | null
		{
			$controller = new \ReflectionClass($this);
			$actions = array_filter($controller->getMethods(\ReflectionMethod::IS_PUBLIC), function ($method) use ($controller) {
				return $method->class == $controller->getName();
			});

			$path = $this->request->vanity;
			if ($this->request->isApiRequest) {
				$path = "{$this->request->apiRoot}{$path}";
			}

			foreach ($actions as $action) {
				$attributes = $action->getAttributes("Melodic\Web\Attributes\Route");

				if (count($attributes) > 0) {
					$route = $attributes[0]->newInstance();

					if (\str_ends_with($path, $route->path)) {
						if (!$httpMethodCheck) {
							return $action->getName();
						}

						if (\strtoupper($route->method->value) == \strtoupper($this->request->method)) {
							return $action->getName();
						}
					}
				}
			}

			return null;
		}

		protected function GetActionName(bool $httpMethodCheck = false): string | null
		{
			$action = $this->request->action;

			if (method_exists($this, $action)) {
				if (!$httpMethodCheck) {
					return $action;
				}

				if (\str_starts_with(\strtoupper($action), \strtoupper($this->request->method))){
					return $action;
				}
			}

			return $this->GetActionByRouteAttribute($httpMethodCheck);
		}

		abstract public function Exec(): Response;

		static function PageNotFound(): Response
		{
			$response = new Response();
			$response->AddHeader("HTTP/1.0 404 Not Found");
			return $response;
		}

		static function JsonException(\Exception $ex)
		{
			header("Content-type: application/json");
			
			die(json_encode(array(
				"success" => false,
				"exception" => array(
					"message" => $ex->getMessage(),
					"code" => $ex->getCode(),
					"file" => $ex->getFile(),
					"line" => $ex->getLine(),
					"trace" => $ex->getTrace()
				)
			)));
		}
	}
}
?>