<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    videoKey: string;
    iframeLink?: string | null;
    title?: string;
}>();

const embedUrl = computed(() => {
    const key = props.iframeLink || props.videoKey;
    if (!key) return '';

    if (key.startsWith('http://') || key.startsWith('https://')) {
        return key;
    }

    return `https://videos.dyntube.com/iframes/${key}`;
});
</script>

<template>
    <div class="dyntube-player-wrapper">
        <iframe
            v-if="embedUrl"
            :src="embedUrl"
            :title="title || 'Dyntube Video Player'"
            class="dyntube-iframe"
            allow="autoplay; fullscreen; picture-in-picture"
            allowfullscreen
        ></iframe>
        <div v-else class="dyntube-placeholder">
            <p>Video ID tidak valid</p>
        </div>
    </div>
</template>

<style scoped>
.dyntube-player-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    background-color: #0f172a;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}
.dyntube-iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.dyntube-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    font-size: 14px;
}
</style>
