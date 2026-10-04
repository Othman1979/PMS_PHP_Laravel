@php
    $selected = (int) old('priority_id', $defaultPriority->id);
    $selectedType = (int) old('fault_type_id');
@endphp
<x-layouts.quick :title="$equipment->name">
    <div class="quick-equip">
        @if ($equipment->photo_url)
            <img src="{{ $equipment->photo_url }}" alt="" class="quick-equip-photo">
        @else
            <span class="quick-equip-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M4 10h16M8 5v2M8 13v3"/></svg>
            </span>
        @endif
        <div class="flex-grow-1 min-w-0">
            <div class="quick-equip-name">{{ $equipment->name }}</div>
            <div class="quick-equip-meta">
                <span>{{ $equipment->code }}</span>
                @if ($equipment->department)<span>{{ $equipment->department->localized_name }}</span>@endif
                @if ($equipment->location)<span>{{ $equipment->location }}</span>@endif
            </div>
            @if ($equipment->isUnderWarranty())
                <span class="badge bg-success mt-1">{{ __('Quick_UnderWarranty') }}</span>
            @endif
        </div>
    </div>

    @if ($openRequests->isNotEmpty())
        <div class="alert alert-warning quick-open">
            <div class="fw-bold mb-1">{{ __('Quick_OpenRequestExists') }}</div>
            @foreach ($openRequests as $r)
                <div>
                    <a href="{{ route('requests.show', $r) }}" class="fw-bold">{{ $r->request_number }}</a>
                    — <x-status-badge :status="$r->status" />
                    @if ($r->assignedTechnician)<span class="small">({{ $r->assignedTechnician->full_name }})</span>@endif
                </div>
            @endforeach
            <div class="small mt-1">{{ __('Quick_OpenRequestHint') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger fw-bold">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('quick.store', $equipment->code) }}" enctype="multipart/form-data" id="quickForm">
        @csrf
        <section class="quick-step">
            <h2 class="quick-step-title"><span class="quick-num">1</span>{{ __('Quick_Step1') }}</h2>
            <div class="quick-chips">
                @foreach ($issues as $issue)
                    <button type="button" class="quick-chip" data-text="{{ $issue }}">{{ $issue }}</button>
                @endforeach
            </div>
            <textarea id="Description" name="description" class="form-control quick-text" rows="3" placeholder="{{ __('Quick_DescPlaceholder') }}" required maxlength="2000">{{ old('description') }}</textarea>
        </section>

        <section class="quick-step">
            <h2 class="quick-step-title"><span class="quick-num">2</span>{{ __('Quick_Step2') }}</h2>
            <div class="quick-prios" style="grid-template-columns: repeat({{ min(3, max(1, $priorities->count())) }}, 1fr)">
                @foreach ($priorities as $p)
                    <label class="quick-prio" style="--prio-color: {{ $p->color }}">
                        <input type="radio" name="priority_id" value="{{ $p->id }}" @checked($selected === $p->id)>
                        <span class="quick-prio-dot"></span>
                        <span class="quick-prio-title">{{ $p->label() }}</span>
                        @if ($p->hint())
                            <span class="quick-prio-hint">{{ $p->hint() }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </section>

        @if ($faultTypes->isNotEmpty())
            <section class="quick-step">
                <h2 class="quick-step-title"><span class="quick-num">3</span>{{ __('Quick_StepFaultType') }} <small class="text-muted fw-normal">{{ __('Optional') }}</small></h2>
                <div class="quick-chips">
                    @foreach ($faultTypes as $t)
                        <label class="quick-chip quick-chip-radio">
                            <input type="radio" name="fault_type_id" value="{{ $t->id }}" @checked($selectedType === $t->id)>
                            {{ $t->localized_name }}
                        </label>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="quick-step">
            <h2 class="quick-step-title"><span class="quick-num">{{ $faultTypes->isNotEmpty() ? 4 : 3 }}</span>{{ __('Quick_Step3') }}</h2>
            <label class="quick-photo" id="photoLabel">
                <input name="photo" type="file" accept="image/*" capture="environment" class="d-none" id="photoInput">
                <img id="photoPreview" alt="" class="d-none">
                <span id="photoText">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    {{ __('Quick_TakePhoto') }}
                </span>
            </label>
        </section>

        <button type="submit" class="btn btn-primary quick-send" id="sendBtn">{{ __('Quick_Send') }}</button>
    </form>

    <x-slot:scripts>
        <script>
            (function () {
                const text = document.getElementById('Description');
                const sep = document.documentElement.dir === 'rtl' ? '، ' : ', ';
                document.querySelectorAll('.quick-chip[data-text]').forEach(chip => {
                    chip.addEventListener('click', () => {
                        const t = chip.dataset.text;
                        const cur = text.value.trim();
                        if (chip.classList.toggle('active')) {
                            text.value = cur ? cur + sep + t : t;
                        } else {
                            text.value = cur.split(/[،,]\s*/).filter(p => p !== t).join(sep);
                        }
                    });
                });
                const input = document.getElementById('photoInput');
                const preview = document.getElementById('photoPreview');
                input.addEventListener('change', () => {
                    const f = input.files && input.files[0];
                    if (!f) { preview.classList.add('d-none'); return; }
                    preview.src = URL.createObjectURL(f);
                    preview.classList.remove('d-none');
                });
                const hasOpen = @js($openRequests->isNotEmpty());
                document.getElementById('quickForm').addEventListener('submit', e => {
                    if (hasOpen && !confirm(@js(__('Quick_DuplicateConfirm')))) { e.preventDefault(); return; }
                    const b = document.getElementById('sendBtn');
                    setTimeout(() => { b.disabled = true; }, 0);
                });
            })();
        </script>
    </x-slot:scripts>
</x-layouts.quick>
