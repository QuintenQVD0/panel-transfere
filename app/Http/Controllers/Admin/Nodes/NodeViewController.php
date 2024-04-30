<?php

namespace App\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\Node;
use Illuminate\Support\Collection;
use App\Models\Allocation;
use App\Http\Controllers\Controller;
use App\Traits\Controllers\JavascriptInjection;
use App\Services\Helpers\SoftwareVersionService;

class NodeViewController extends Controller
{
    use JavascriptInjection;

    public const THRESHOLD_PERCENTAGE_LOW = 75;
    public const THRESHOLD_PERCENTAGE_MEDIUM = 90;

    /**
     * NodeViewController constructor.
     */
    public function __construct(
        private SoftwareVersionService $versionService,
    ) {
    }

    /**
     * Returns index view for a specific node on the system.
     */
    public function index(Request $request, Node $node): View
    {
        $node->loadCount('servers');

        $stats = Node::query()
            ->selectRaw('IFNULL(SUM(servers.memory), 0) as sum_memory, IFNULL(SUM(servers.disk), 0) as sum_disk')
            ->join('servers', 'servers.node_id', '=', 'nodes.id')
            ->where('node_id', '=', $node->id)
            ->first();

        return view('admin.nodes.view.index', [
            'node' => $node,
            'version' => $this->versionService,
        ]);
    }

    /**
     * Returns the settings page for a specific node.
     */
    public function settings(Request $request, Node $node): View
    {
        return view('admin.nodes.view.settings', [
            'node' => $node,
        ]);
    }

    /**
     * Return the node configuration page for a specific node.
     */
    public function configuration(Request $request, Node $node): View
    {
        return view('admin.nodes.view.configuration', compact('node'));
    }

    /**
     * Return the node allocation management page.
     */
    public function allocations(Request $request, Node $node): View
    {
        $node->setRelation(
            'allocations',
            $node->allocations()
                ->orderByRaw('server_id IS NOT NULL DESC, server_id IS NULL')
                ->orderByRaw('INET_ATON(ip) ASC')
                ->orderBy('port')
                ->with('server:id,name')
                ->paginate(50)
        );

        $this->plainInject(['node' => Collection::wrap($node)->only(['id'])]);

        return view('admin.nodes.view.allocation', [
            'node' => $node,
            'allocations' => Allocation::query()->where('node_id', $node->id)
                ->groupBy('ip')
                ->orderByRaw('INET_ATON(ip) ASC')
                ->get(['ip']),
        ]);
    }

    /**
     * Return a listing of servers that exist for this specific node.
     */
    public function servers(Request $request, Node $node): View
    {
        $this->plainInject([
            'node' => Collection::wrap($node->makeVisible(['daemon_token_id', 'daemon_token']))
                ->only(['scheme', 'fqdn', 'daemon_listen', 'daemon_token_id', 'daemon_token']),
        ]);

        return view('admin.nodes.view.servers', [
            'node' => $node,
            'servers' => $node->servers()->with(['user', 'egg'])->paginate(25),
        ]);
    }
}
