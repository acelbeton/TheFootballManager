@props([
    'label' => 'Select an option',
    'name',
    'options' => [],  // asszociatív tömböt vár [value => display text]
    'default' => null
])

<div class="select-group mb-3" x-data="{
        open: false,
        selectedText: '{{ $label }}',
        updateText() {
            const select = this.$refs.select;
            if (select.selectedIndex >= 0) {
                this.selectedText = select.options[select.selectedIndex].text;
            }
        },
        init() {
            this.$nextTick(() => this.updateText());
        }
    }"
wire:ignore.self
>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        class="hidden"
        x-ref="select"
        x-on:change="updateText()"
        {{ $attributes }}
        required
    >
        <option value="" disabled>{{ $label }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" {{ $value == $default ? 'selected' : '' }}>{{ $text }}</option>
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
                $refs.select.dispatchEvent(new Event('change'));
                open = false"
            >
                {{ $text }}
            </div>
        @endforeach
    </div>
    <label for="{{ $name }}" class="select-label">{{ $label }}</label>
</div>
