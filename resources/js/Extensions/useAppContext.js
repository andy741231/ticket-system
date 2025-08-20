import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAppContext() {
  const page = usePage();
  const currentApp = computed(() => page.props.appContext?.currentApp || null);
  const teamId = computed(() => page.props.appContext?.teamId ?? null);
  const appSlug = computed(() => currentApp.value?.slug || null);

  return { currentApp, appSlug, teamId };
}
