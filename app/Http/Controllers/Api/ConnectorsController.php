<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\IngestRun;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\Connectors\ConnectorAdapterFactory;
use App\Services\Connectors\DocumentIngester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConnectorsController extends Controller
{
    public function __construct(
        private readonly DocumentIngester $ingester,
        private readonly ConnectorAdapterFactory $factory,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connectors = $workspace->connectors()->orderBy('kind')->get();
        return response()->json(['data' => $connectors->map(fn (Connector $c) => $this->serialize($c))->all()]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);
        return response()->json($this->serialize($connector));
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $data = Validator::make($request->all(), [
            'kind' => ['required', 'string', 'in:'.implode(',', [Connector::KIND_DRIVE, Connector::KIND_NOTION, Connector::KIND_SLACK])],
            'label' => ['nullable', 'string', 'max:120'],
            'config' => ['nullable', 'array'],
        ])->validate();

        $connector = Connector::create([
            'workspace_id' => $workspace->id,
            'kind' => $data['kind'],
            'label' => $data['label'] ?? null,
            'config' => $data['config'] ?? new \stdClass(),
            'status' => Connector::STATUS_ACTIVE,
        ]);

        AuditLogger::record($workspace, 'connector', $connector->id, 'created', ['kind' => $connector->kind], request: $request);
        return response()->json($this->serialize($connector), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);
        $data = Validator::make($request->all(), [
            'label' => ['nullable', 'string', 'max:120'],
            'config' => ['nullable', 'array'],
        ])->validate();
        $connector->fill($data);
        $connector->save();
        AuditLogger::record($workspace, 'connector', $connector->id, 'updated', $data, request: $request);
        return response()->json($this->serialize($connector));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);
        $this->factory->forConnector($connector)->revoke($connector);
        $connector->delete();
        AuditLogger::record($workspace, 'connector', $id, 'deleted', request: $request);
        return response()->json(status: 204);
    }

    public function reindex(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);

        $mode = $request->input('mode', IngestRun::MODE_INCREMENTAL);
        if (! in_array($mode, [IngestRun::MODE_INCREMENTAL, IngestRun::MODE_FULL], true)) {
            $mode = IngestRun::MODE_INCREMENTAL;
        }
        if ($mode === IngestRun::MODE_FULL) {
            $workspace->forceFill(['degraded_until' => now()->addMinutes(5)])->save();
        }
        $run = $this->ingester->run($workspace, $connector, $mode);
        if ($mode === IngestRun::MODE_FULL) {
            $workspace->forceFill(['degraded_until' => null])->save();
        }
        AuditLogger::record($workspace, 'connector', $connector->id, 'reindex', ['mode' => $mode, 'run_id' => $run->id], request: $request);
        return response()->json(['run_id' => $run->id, 'status' => $run->status], 202);
    }

    public function pause(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);
        $connector->forceFill(['status' => Connector::STATUS_PAUSED, 'paused_at' => now()])->save();
        AuditLogger::record($workspace, 'connector', $connector->id, 'paused', request: $request);
        return response()->json($this->serialize($connector));
    }

    public function resume(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $connector = $workspace->connectors()->findOrFail($id);
        $connector->forceFill([
            'status' => Connector::STATUS_ACTIVE,
            'paused_at' => null,
            'backoff_until' => null,
            'consecutive_failures' => 0,
        ])->save();
        AuditLogger::record($workspace, 'connector', $connector->id, 'resumed', request: $request);
        return response()->json($this->serialize($connector));
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    private function serialize(Connector $c): array
    {
        return [
            'id' => $c->id,
            'kind' => $c->kind,
            'label' => $c->label,
            'status' => $c->status,
            'config' => $c->config,
            'last_sync_at' => $c->last_sync_at?->toIso8601String(),
            'backoff_until' => $c->backoff_until?->toIso8601String(),
            'paused_at' => $c->paused_at?->toIso8601String(),
            'consecutive_failures' => $c->consecutive_failures,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
