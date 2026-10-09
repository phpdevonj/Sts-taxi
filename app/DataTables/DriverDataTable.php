<?php

namespace App\DataTables;

use App\Models\User;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class DriverDataTable extends DataTable
{
    use DataTableTrait {
        getBuilderParameters as baseBuilderParameters;
    }
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            
            ->editColumn('status', function($query) {
                $status = 'warning';
                switch ($query->status) {
                    case 'active':
                        $status = 'primary';
                        break;
                    case 'inactive':
                        $status = 'danger';
                        break;
                    case 'banned':
                        $status = 'dark';
                        break;
                }
                return '<span class="text-capitalize text-' .$status .' badge badge-light-'.$status.'">'.$query->status.'</span>';
            })
            ->editColumn('display_name', function ($query) {
                return '<a href="'.route('driver.show',$query->id).'">'.$query->display_name.'</span></a>';

            })
            ->editColumn('is_verified_driver', function($driver) {

                $is_verified_driver = $driver->is_verified_driver;
                if( $is_verified_driver == '1' ){
                    $status = '<span class="badge text-success badge-light-success">'.__('message.verified').'</span>';
                }else{
                    $status = '<span class="badge text-warning badge-light-warning">'.__('message.unverified').'</span>';
                }
                return $status;
            })
            ->editColumn('service_id' , function ( $query ) {
                $services = $this->driverServiceList($query);
                if ($services->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }
                $first = $services->first();
                $html = '<span class="driver-service-summary" title="'.e($services->pluck('label')->implode(', ')).'">'
                    .'<span class="badge badge-light-'.$first['color'].' text-'.$first['color'].' text-truncate driver-service-badge">'.e($first['label']).'</span>';
                if ($services->count() > 1) {
                    $html .= ' <button type="button" class="badge rounded-pill bg-light text-dark border-0 driver-row-toggle" aria-label="'.e(__('message.show_details')).'">'
                        .__('message.more_count', ['count' => $services->count() - 1]).'</button>';
                }
                return $html.'</span>';
            })
            ->editColumn('address', function ($query) {
                if (!$query->address) {
                    return '-';
                }
                return '<span class="d-inline-block text-truncate driver-address" title="'.e($query->address).'">'.e($query->address).'</span>';
            })
            ->addColumn('services_text', function ($query) {
                return $this->driverServiceList($query)->pluck('label')->implode(', ');
            })
            ->addColumn('expand', function ($query) {
                if ($this->driverServiceList($query)->count() <= 1) {
                    return '';
                }
                return '<button type="button" class="btn btn-sm btn-link p-0 text-dark driver-row-toggle" aria-expanded="false" aria-label="'.e(__('message.show_details')).'"><i class="fas fa-chevron-right"></i></button>';
            })
            ->addColumn('details', function ($query) {
                return view('driver.list-details', [
                    'driver'   => $query,
                    'services' => $this->driverServiceList($query),
                ])->render();
            })

            ->editColumn('contact_number', function ($query) {
                if (!$query->contact_number) {
                    return 'Not Available';
                }

                $number = formatPhoneNumber($query->contact_number);

                return maskSensitiveInfo('contact_number', $number, 'Not Available');
            })
            
            ->filterColumn('service_id', function( $query, $keyword ){
                $query->whereHas('approvedDriverServices.service', function ($q) use($keyword){
                    $q->where('name', 'like' , '%'.$keyword.'%');
                });
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('last_actived_at', function ($query) {
                return dateAgoFormate($query->last_actived_at, true);
            })
            ->addIndexColumn()
            // ->addColumn('action', 'driver.action')
            ->addColumn('action', function($data){
                $id = $data->id;
                return view('driver.action',compact('data','id'))->render();
            })
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];

                    $column_name = 'created_at';
                    $direction = 'desc';
                    if( $column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }
    
                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns(['action','status', 'is_verified_driver','display_name','service_id','address','expand','details']);
    }

    /**
     * Driver's approved services as [label, color] pairs; falls back to the legacy
     * single service_id only for drivers that have no driver_services rows at all.
     */
    protected function driverServiceList($driver)
    {
        $services = $driver->approvedDriverServices->filter(function ($ds) {
            return optional($ds->service)->name;
        })->map(function ($ds) {
            return ['label' => $ds->service->name, 'color' => 'success'];
        })->values();

        if ($driver->driver_services_count == 0 && $driver->service_id != null && optional($driver->service)->name) {
            $services->push(['label' => $driver->service->name, 'color' => 'success']);
        }
        return $services;
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\User $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        $model = User::where('user_type','driver')->with('approvedDriverServices.service')->withCount('driverServices');
        if(auth()->user()->hasRole('fleet')) {
            $model->where('fleet_id', auth()->user()->id);
        }
        if (request()->driver_id) {
            $model->where('id', request()->input('driver_id'));
        }
        if (request()->service_id) {
            $model->whereHas('approvedDriverServices', function ($q) { $q->where('service_id', request()->input('service_id')); });
        }
        if (request()->contact_number) {
            $model->where('contact_number', 'like', '%' . request()->input('contact_number') . '%');
        }  
        $last_active = isset($_GET['last_actived_at']) ? $_GET['last_actived_at'] : null;
        if ($last_active != null) {
            if ($last_active == 'active_user') {
                $model = $model->whereDate('last_actived_at',  now());
            } elseif ($last_active == 'engaged_user') {
                $model = $model->where('last_actived_at', '<', now()->subDay())->where('last_actived_at', '>', now()->subDays(15));
            } elseif ($last_active == 'inactive_user') {
                $model = $model->where('last_actived_at', '<=', now()->subDays(15))->orWhereNull('last_actived_at');
            }
        }

        if($this->status != null){
            // $model = $model->where('status', $this->status);
            $model = $model->where('status', '!=', 'active');
        } else {
            $model = $model->where('status','active');
        }

        return $this->applyScopes($model);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::computed('expand')
                ->title('')
                ->exportable(false)
                ->printable(false)
                ->width(20)
                ->addClass('text-center'),
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false)
                ->width(60),
            Column::make('display_name')->title( __('message.name') ),
            Column::make('contact_number'),
            Column::make('address'),
            Column::make('service_id')->title( __('message.service') ),
            Column::make('last_actived_at'),
            Column::make('created_at')->title( __('message.created_at') ),
            Column::make('is_verified_driver')->title( __('message.is_verify') ),
            Column::make('status'),
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(60)
                  ->addClass('text-center'),
        ];
    }

    /**
     * Export the full service list instead of the compact badge summary.
     */
    public function getBuilderParameters(): array
    {
        $parameters = $this->baseBuilderParameters();
        $exportOptions = [
            'columns' => ':visible:not(:first-child)',
            'format' => [
                'body' => "function (data, row, column, node) {
                    if ($(node).find('.driver-service-summary').length) {
                        return $('#dataTableBuilder').DataTable().row(row).data().services_text;
                    }
                    return $('<div>').html(data).text().trim();
                }",
            ],
        ];
        foreach ($parameters['buttons'] as &$button) {
            $button['exportOptions'] = $exportOptions;
        }
        return $parameters;
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'driver_' . date('YmdHis');
    }
}
