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

		public function Html(string $html): Response
		{
			$response = new Response();
			$response->SetContentType("text/html");
			$response->SetContent($html);

			return $response;
		}
		
		public function Json($obj): Response
		{
			$response = new Response();
			$response->SetContentType("application/json");
			$response->SetContent(json_encode($obj));

			return $response;
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