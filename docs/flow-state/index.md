## Flow State
> The flow state class is responsible for keeping the state of a request or asset.
> The user can define new states for a flow and an asset can be in one of these states and go to another state with a transition.
> Flow state can be of type `start` , `end` or `middle` and they can keep a status in themselves. For example, a request (or asset) can be in the state related to the approval of the financial manager and its status is rejected, which means that the financial manager can come to this state and change the status to different values such as approved or rejected.
><br><br>
> the flow states also have names that is stored in translations like: `Awaiting financial unit approval` or `در انتظار تایید واحد مالی`

## ّFlowState Facade
this class is responsible for create, update, show and delete of the flow states.<br>
The methods includes this table:
