                                    @include('pages.attendances.lunch-regularization')
                                    <span class="badge {{ ['approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', 'pending' => 'text-bg-warning'][$row['status']] ?? 'text-bg-secondary' }}">{{ ($row['unmarked'] ?? false) ? 'Sin marcaciones' : $statuses[$row['status']] }}</span>
                                    @if(!$row['complete'])<div class="text-muted mt-2">{{ $row['reason'] }}</div>@endif
                                    @if($row['complete'] && $row['raw_extra'] > 0 && !$row['eligible'])<div class="text-muted mt-2">No alcanza el mínimo configurado.</div>@endif
                                    @if($row['status'] === 'pending')
                                        <div class="mt-2 fw-semibold">Tiempo extra a revisar: {{ $duration($row['eligible']) }}</div>
                                        <form method="POST" action="{{ route('attendances.overtime-review', $row['exit']) }}" class="mt-2">
                                            @csrf
                                            <input type="hidden" name="fingerprint" value="{{ $row['fingerprint'] }}">
                                            <label for="note_{{ $row['exit']->id }}" class="form-label">Observación (obligatoria al rechazar)</label>
                                            <textarea id="note_{{ $row['exit']->id }}" name="note" maxlength="500" class="form-control form-control-sm mb-2" rows="2"></textarea>
                                            <div class="d-flex gap-2"><button name="decision" value="approved" class="btn btn-sm btn-success">Aprobar extras</button><button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Rechazar extras</button></div>
                                        </form>
                                    @elseif(in_array($row['status'], ['approved', 'rejected']))
                                        <div class="text-muted mt-2">{{ $row['exit']->reviewer?->name ?? 'Usuario no disponible' }}<br>{{ $row['exit']->overtime_reviewed_at?->format('d/m/Y H:i') }}</div>
                                        <div>{{ $row['exit']->overtime_review_note }}</div>
                                    @endif
