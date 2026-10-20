# Committed business outcomes

Classes are registered by the package provider alongside legacy events. Keys ending in `.committed` are safe automation outcomes; earlier event signatures, payloads and timing are preserved. Only scalar identifiers are exposed.

| Event class | Key | Payload properties |
| --- | --- | --- |
| `JobMetric\Flow\Events\Flow\FlowStoreCommittedEvent` | `flow.stored.committed` | `id: int|string` |
| `JobMetric\Flow\Events\Flow\FlowUpdateCommittedEvent` | `flow.updated.committed` | `id: int|string` |
| `JobMetric\Flow\Events\Flow\FlowDeleteCommittedEvent` | `flow.deleted.committed` | `id: int|string` |
| `JobMetric\Flow\Events\Flow\FlowRestoreCommittedEvent` | `flow.restored.committed` | `id: int|string` |
| `JobMetric\Flow\Events\Flow\FlowForceDeleteCommittedEvent` | `flow.force_deleted.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowState\FlowStateStoreCommittedEvent` | `flow_state.stored.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowState\FlowStateUpdateCommittedEvent` | `flow_state.updated.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowState\FlowStateDeleteCommittedEvent` | `flow_state.deleted.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTask\FlowTaskStoreCommittedEvent` | `flow_task.stored.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTask\FlowTaskUpdateCommittedEvent` | `flow_task.updated.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTask\FlowTaskDeleteCommittedEvent` | `flow_task.deleted.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreCommittedEvent` | `flow_transition.stored.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTransition\FlowTransitionUpdateCommittedEvent` | `flow_transition.updated.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTransition\FlowTransitionDeleteCommittedEvent` | `flow_transition.deleted.committed` | `id: int|string` |
| `JobMetric\Flow\Events\FlowTransition\FlowTransitionExecuteCommittedEvent` | `workflow.flow_transition.executed.committed` | `id: int|string`, `subjectType: string`, `subjectId: int|string` |
| `JobMetric\Flow\Events\FlowBinding\FlowBindingAttachCommittedEvent` | `workflow.flow_binding.attached.committed` | `ownerType: string`, `ownerId: int|string`, `flowId: int` |
| `JobMetric\Flow\Events\FlowBinding\FlowBindingDetachCommittedEvent` | `workflow.flow_binding.detached.committed` | `ownerType: string`, `ownerId: int|string`, `flowId: int` |

Schedule from the owning operation after successful persistence. Delivery waits for the owning connection outer commit and is discarded on rollback. No-op operations emit nothing. Native Laravel listeners are isolated individually; failures are logged with event key and exception class only. No models, raw inputs, credentials or translated content are included. This is synchronous delivery without durable outbox guarantees.
