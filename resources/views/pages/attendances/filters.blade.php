    <form method="GET" action="{{ route($filterRoute) }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-sm-6 col-lg-2"><label for="from" class="form-label">Desde</label><input type="date" id="from" name="from" class="form-control" value="{{ $from }}" required></div>
            <div class="col-sm-6 col-lg-2"><label for="to" class="form-label">Hasta</label><input type="date" id="to" name="to" class="form-control" value="{{ $to }}" required></div>
            <div class="col-sm-6 col-lg-3"><label for="employee_id" class="form-label">Empleado</label><select id="employee_id" name="employee_id" class="form-select"><option value="">Todos</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->first_name }} {{ $employee->last_name_father }} · {{ $employee->document_number }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-lg-3"><label for="branch_id" class="form-label">Sede registrada</label><select id="branch_id" name="branch_id" class="form-select"><option value="">Todas</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary">{{ $filterRoute === 'reports.attendances' ? 'Generar reporte' : 'Filtrar' }}</button><a href="{{ route($filterRoute) }}" class="btn btn-outline-secondary">Limpiar</a></div>
        </div>
    </form>
