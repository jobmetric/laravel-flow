## Flow

to work with flows in your app you must first create a new `flow driver` with this artisan command:

```bash
php artisan make:flow [DriverName]
```

> **What is a Flow Driver?**<br>
> Flow Driver is a class that handle three actions:
> - check the transition between two states is possible or not
> - get all possible statuses for each state
> - accomplish transition and run tasks for that transition
>
driver name must be an unique singular name like `request` or `payroll`.<br><br>
this command will create a file name `[DriverName]DriverFlow.php` in App/Flows/Drivers/[DriverName] directory.
for example the command `php artisan make:flow request` will create RequestDriverFlow.php in App/Flows/Drivers/Request

this class contains below code:

```php
namespace App\Flows\Drivers\Request;

use JobMetric\Flow\Abstracts\DriverContract;
use JobMetric\Flow\Models\FlowState;

class RequestDriverFlow extends DriverContract
{
    public function allowTransition(FlowState $from, FlowState $to)
    {
        
    }
    
    public function transition(FlowState $from, FlowState $to,int $assetId){
        
    }

    public function getStatus(): array
    {
        return [
            // write any status here
        ];
    }
}
```

this class contains the following methods:
>
>**getStatus()** : Returning all possible statuses in each state as an array  
> **allowTransition()** check this transition is allowed if not an exception will be thrown  
> **transition()** in this method, you have to check the level of access, execute pipeline tasks, and change the current state of the asset to the `to` state.

## Flow Facade

to work easily with crud operation of a flow you can use the `Flow` Facade that contains following methods:

| method                                 | desc                                                             | 
|----------------------------------------|------------------------------------------------------------------|
| `store(array $data)`                   | store a flow                                                     | 
| `show(int $flow_id, array $with = [])` | return a flow with loading relations                             | 
| `update(int $flow_id, array $data)`    | update a flow                                                    | 
| `delete(int $flow_id)`                 | delete a flow                                                    | 
| `restore(int $flow_id)`                | restore a flow                                                   |
| `forceDelete(int $flow_id)`            | force delete a flow                                              |
| `getDriver(string $driver)`            | get driver of a flow by driver name and return as DriverContract |
| `getStatus(string $driver)`            | get all possible statuses of flow by driver name                 |
| `getStartState(int $flow_id)`          | get start state of a flow by flow_id                             |

this facade is using FlowManager Class, so you can directly use FlowManager class instead of Flow Facade
