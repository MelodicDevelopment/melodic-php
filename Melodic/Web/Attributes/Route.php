<?php
namespace Melodic\Web\Attributes
{
	use Melodic\Web\Enums\Method;

	#[\Attribute]
	class Route
	{
		public string $path;
		public METHOD $method;

		public function __construct(string $path, METHOD $method = METHOD::GET)
		{
			if (strlen($path) < 1) {
				throw new \Exception("No path provided for route attribute");
			}

			if (strlen($method->value) < 1) {
				throw new \Exception("No method provided for route attribute");
			}

			$this->path = $path;
			$this->method = $method;
		}
	}
}
?>