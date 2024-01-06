<?php
namespace Melodic\Web\Interfaces
{
	use \Melodic\Web\Request;
	use \Melodic\Web\Response;

	interface iRequestHook
	{
		public function Invoke(Request $request): Response;
	}
}
?>