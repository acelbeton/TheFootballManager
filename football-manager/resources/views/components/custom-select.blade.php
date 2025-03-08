@props([
    'label' => 'Select an option',
    'name',
    'options' => []  // asszociatív tömböt vár [value => display text]
])

<div class="select-group mb-3" x-data="{ open: false, selectedText: '{{ $label }}' }" wire:ignore>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        class="hidden"
        x-ref="select"
        x-on:change="selectedText = $refs.select.options[$refs.select.selectedIndex].text"
        {{ $attributes->whereStartsWith('wire:model') }}
        required
    >
        <option value="" selected disabled>{{ $label }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>

    <div class="custom-select" x-on:click="open = !open" :class="{'active': open}">
        <span x-text="selectedText"></span>
        <div class="select-arrow" :class="{'open': open}"></div>
    </div>

    <div class="custom-options" x-show="open" x-on:click.away="open = false">
        @foreach ($options as $value => $text)
            <div
                class="custom-option"
                x-on:click="
                $refs.select.value = '{{ $value }}';
                selectedText = @js($text);
                $refs.select.dispatchEvent(new Event('change'));
                open = false"
            >
                {{ $text }}
            </div>
        @endforeach
    </div>
    <label for="{{ $name }}" class="select-label">{{ $label }}</label>
</div>
