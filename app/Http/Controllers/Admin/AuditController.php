<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderBy('created_at', 'desc');

        // Filtros
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $audits = $query->paginate(20);
        $actions = AuditLog::distinct('action')->pluck('action');

        // Datos para gráficas
        // Acciones por tipo
        $actionCounts = AuditLog::raw(function($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$action', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 10]
            ]);
        });

        $actionLabels = [];
        $actionData = [];
        foreach ($actionCounts as $item) {
            $actionLabels[] = ucfirst($item->_id);
            $actionData[] = $item->count;
        }

        // Actividad por día (últimos 7 días)
        $dailyActivity = AuditLog::raw(function($collection) {
            $sevenDaysAgo = new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000);
            return $collection->aggregate([
                ['$match' => ['created_at' => ['$gte' => $sevenDaysAgo]]],
                ['$group' => [
                    '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['_id' => 1]]
            ]);
        });

        $dailyLabels = [];
        $dailyData = [];
        foreach ($dailyActivity as $item) {
            $dailyLabels[] = date('d/m', strtotime($item->_id));
            $dailyData[] = $item->count;
        }

        // Usuarios más activos
        $topUsers = AuditLog::raw(function($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$user_name', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 5]
            ]);
        });

        $userLabels = [];
        $userData = [];
        foreach ($topUsers as $item) {
            $userLabels[] = $item->_id;
            $userData[] = $item->count;
        }

        return view('admin.audits.index', compact(
            'audits', 
            'actions',
            'actionLabels',
            'actionData',
            'dailyLabels',
            'dailyData',
            'userLabels',
            'userData'
        ));
    }
}
