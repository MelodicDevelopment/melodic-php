<?php
namespace TestWeb\Controllers
{
	use Melodic;
	use Melodic\DependencyInjection\Attributes\Inject;
	use Melodic\Web\Request;
	use Melodic\Web\Controllers\MvcController;

	class Home extends MvcController
	{
		private \TestWeb\Services\TestService $_testService;

		public function __construct(
			Request $request,
			#[Inject("TestWeb\Services\TestService")] \TestWeb\Services\TestService $testService
		)
		{
			parent::__construct($request);

			$this->_testService = $testService;
		}

		public function Index(): string
		{
			return $this->View($this->_testService->TestMe());
		}
	}
}
?>