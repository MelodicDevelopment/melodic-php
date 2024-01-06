<?php
namespace TestWeb\API\Controllers
{
	use Melodic\Attributes;
	use Melodic\Web\Controllers\ApiController;

	class Test extends ApiController
	{
		public function Index(): array
		{
			$test = array(
				1 => "record 1",
				2 => "record 2",
				3 => "record 3",
				4 => "record 4"
			);
			return $test;
		}
	}
}
?>