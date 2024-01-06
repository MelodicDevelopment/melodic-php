<?php
namespace Melodic\DependencyInjection
{
	class Module
	{
		public string $token;
		public mixed $implementation;
		public bool $singleton;
		public mixed $instance;

		public function __construct(string $token, mixed $implementation, bool $singleton = false)
		{
			$this->token = $token;
			$this->implementation = $implementation;
			$this->singleton = $singleton;
		}
	}
}
?>