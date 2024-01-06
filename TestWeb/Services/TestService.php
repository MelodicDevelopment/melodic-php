<?php

namespace TestWeb\Services
{
	use Melodic\DependencyInjection\Attributes\Injectable;

	#[Injectable("TestWeb\Services\TestService", TestService::class)]
	class TestService
	{
		public function TestMe()
		{
			return [1 => "Test 1", 2 => "Test 2", 3 => "Test 3"];
		}
	}
}
?>