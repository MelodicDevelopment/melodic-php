<?php
namespace TestWeb
{
	require_once "Melodic/Core.php";

	use Melodic\Config;
	use Melodic\DependencyInjection\InjectionEngine;
	use Melodic\Web\RequestPipeline;
	use Melodic\Web\Request;
	use Melodic\Web\Response;
	use Melodic\Web\Interfaces\iRequestHook;

	InjectionEngine::RegisterFromLibrary(Config::Get("appRoot"), __DIR__, [".", "..", basename(__FILE__)]);


	$tempHook = new class implements iRequestHook {
		private ?iRequestHook $_next;

		public function __construct(iRequestHook $next = null)
		{
			$this->_next = $next;
		}

		public function Invoke(Request $request): Response
		{
			return $this->_next->Invoke($request);
		}
	};


	/** configure pipeline */
	$pipeline = new RequestPipeline();

	$pipeline->RegisterHook($tempHook::class);

	$pipeline->Exec();
}
?>