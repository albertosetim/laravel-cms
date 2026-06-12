{{-- Template default: uma zona única com o documento de blocos da revision. --}}
<x-site-layout :page="$page" :is-preview="$isPreview">
    <x-cms.blocks :revision="$revision" />
</x-site-layout>
