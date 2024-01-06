<?php
namespace Melodic\Web\Controllers
{
	use \Melodic\Web\Response;

	abstract class ApiController extends Controller
	{
		public function Exec(): Response
		{
			$action = $this->request->action;
			
			if (method_exists($this, $action)){
				if ($this->request->method == "POST" || $this->request->method == "PUT"){
					$model = null;
					
					if ($this->request->method == "POST") {
						$model = $_POST;
					} else {
						parse_str(file_get_contents('php://input'), $model);
					}

					array_unshift($this->request->params, $model);
				}

				foreach ($this->request->query as $key => $value) {
					$this->request->params[$key] = $value;
				}

				$result = call_user_func_array(array($this, $action), $this->request->params);

				return $this->Json($result);
			}
			
			return Controller::PageNotFound();
		}
	}
}
?>