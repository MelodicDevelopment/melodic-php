<?php
namespace TestWeb\API\Controllers
{
	use Melodic\Attributes;
	use Melodic\Web\Attributes\Route;
	use Melodic\Web\Enums\Method;
	use Melodic\Web\Controllers\ApiController;

	class Test extends ApiController
	{
		#[Route("/", METHOD::GET)]
		public function Get(): array
		{
			$test = array(
				1 => "record 1",
				2 => "record 2",
				3 => "record 3",
				4 => "record 4"
			);
			return $test;
		}

		#[Route("get", METHOD::GET)]
		public function GetSomeMore(): array
		{
			$test = array(
				1 => "record 5",
				2 => "record 6",
				3 => "record 7",
				4 => "record 8"
			);
			return $test;
		}
	}
}
?>