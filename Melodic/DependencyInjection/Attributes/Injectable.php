<?php
namespace Melodic\DependencyInjection\Attributes
{
	use Melodic\DependencyInjection\InjectionEngine;
	use Melodic\DependencyInjection\Module;

	#[\Attribute]
	class Injectable
	{
		public string $token;
		public mixed $implementation;
		public bool $singleton;

		public function __construct(string $token, mixed $implementation, bool $singleton = false)
		{
			$this->token = $token;
			$this->implementation = $implementation;
			$this->singleton = $singleton;

			if ($this->implementation == null) {
				throw new \Exception("Must supply an implementation.");
			}

			InjectionEngine::Register(new Module($this->token, $this->implementation, $this->singleton));
		}
	}
}
?>