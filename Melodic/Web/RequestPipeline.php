<?php
namespace Melodic\Web
{
	use \Melodic;
	use \Melodic\Config;
	use \Melodic\Attributes;
	use \Melodic\Web\Controllers\Controller;
	use \Melodic\Web\Interfaces\iRequestHook;

	class RequestPipeline
	{
		private array $_requestHooks = array();
		private Request $_request;
		
		public function __construct()
		{
			$this->RegisterMelodicInjectables();

			$this->_request = new Request();
		}

		public function Exec(): void
		{
			$invokeClass = new class implements iRequestHook {
				private ?iRequestHook $_next;

				public function __construct(iRequestHook $next = null)
				{
					$this->_next = $next;
				}

				public function Invoke(Request $request): Response
				{
					$controllerDirectory = Config::Get("controllers");

					if ($request->isApiRequest){
						$controllerDirectory = Config::Get("apiControllers");
					}
		
					if (file_exists($controllerDirectory."/".$request->controller.".php")){
						$controllerInstanceName = Config::Get("appRoot")."\\".str_replace("/", "\\", $controllerDirectory)."\\".$request->controller;
						$controllerInstance = $controllerInstanceName::InstantiateByInjection([$request]);
						$response = $controllerInstance->Exec();
					} else {
						$response = Controller::PageNotFound();
					}

					return $response;
				}
			};

			$this->_requestHooks[] = $invokeClass::class;

			$pipeline = $this->CreateRequestPipeline($this->_requestHooks[0], 1);
			$response = $pipeline->Invoke($this->_request);
			$response->Render();
		}

		public function RegisterHook(mixed $hook): void
		{
			$this->_requestHooks[] = $hook;
		}

		private function CreateRequestPipeline(mixed $currentHook, ?int $nextHookIndex = null)
		{
			$nextHook = null;

			if (array_key_exists($nextHookIndex, $this->_requestHooks)) {
				$nextHookClass = $this->_requestHooks[$nextHookIndex];
				$nextHookIndex++;
				
				$nextHook = $this->CreateRequestPipeline($nextHookClass, $nextHookIndex);
				
			}

			return new $currentHook($nextHook);
		}

		private function RegisterMelodicInjectables(): void
		{
			$melodicDirectoryRoot = __DIR__."/../../Melodic";
			$melodicRootNamespace = "Melodic";
			$ignoredItems = array(".", "..", "Core.php");
			$injectableAttributeName = "Melodic\\Attributes\\Injectable";

			$melodicClasses = Melodic\Utility\LibraryHelper::GetClasses($melodicRootNamespace, $melodicDirectoryRoot, $ignoredItems);
			$melodicInjectables = Melodic\Utility\LibraryHelper::GetClassesByAttribute($melodicClasses, $injectableAttributeName);
		}

	}
}
?>