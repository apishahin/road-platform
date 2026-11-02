@props(['issue'])

@php($s = \App\Support\IssuePresenter::display($issue))

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium']) }}
      style="background-color: {{ $s['color'] }}1a; color: {{ $s['color'] }}">
    <span class="size-1.5 rounded-full" style="background-color: {{ $s['color'] }}"></span>
    {{ $s['label'] }}
</span>