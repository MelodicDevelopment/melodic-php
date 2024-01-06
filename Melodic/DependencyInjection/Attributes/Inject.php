<?php
namespace Melodic\DependencyInjection\Attributes
{
	#[\Attribute]
	class Inject
	{
		public string $token;

		public function __construct(string $token)
		{
			$this->token = $token;
		}
	}
}
?>