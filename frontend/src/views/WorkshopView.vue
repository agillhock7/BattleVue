<template>
  <section class="grid two">
    <article class="panel col">
      <h2>Inventory</h2>
      <div class="list">
        <div class="card" v-for="item in inventory" :key="item.slug">
          <strong>{{ item.name }}</strong>
          <div class="muted">{{ item.item_type }} | Qty {{ item.quantity }}</div>
        </div>
      </div>

      <h3>Blueprints</h3>
      <div class="list">
        <button class="ghost" v-for="bp in blueprints" :key="bp.id" @click="loadBlueprint(bp)">{{ bp.name }}</button>
      </div>

      <h3>Rulesets</h3>
      <div class="list">
        <button class="ghost" v-for="rs in rulesets" :key="rs.id" @click="loadRuleset(rs)">{{ rs.name }}</button>
      </div>
    </article>

    <article class="col" style="gap: 14px;">
      <BlueprintBuilder v-model="blueprintDraft" />
      <div class="row">
        <button @click="saveBlueprint">Save Blueprint</button>
        <button class="ghost" @click="validateBlueprint">Validate</button>
      </div>

      <RuleBuilder v-model="rulesetDraft" />
      <div class="row">
        <button @click="saveRuleset">Save Ruleset</button>
        <button class="ghost" @click="validateRuleset">Validate</button>
      </div>

      <p class="muted">{{ status }}</p>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import BlueprintBuilder from '@/components/BlueprintBuilder.vue';
import RuleBuilder from '@/components/RuleBuilder.vue';
import { api } from '@/services/api';
import { loadDraft, saveDraft } from '@/services/idb';

const inventory = ref<any[]>([]);
const blueprints = ref<any[]>([]);
const rulesets = ref<any[]>([]);
const status = ref('');

const blueprintDraft = ref<any>({
  name: '',
  chassis: 'chassis-starter',
  lane_pref: 'adaptive',
  modules: [],
  stats: { hp: 100, speed: 10, power: 10 }
});
const rulesetDraft = ref<any>({ name: '', rules: [] });

let editingBlueprintId = 0;
let editingRulesetId = 0;

onMounted(async () => {
  await Promise.all([loadInventory(), loadBlueprints(), loadRulesets(), hydrateDrafts()]);
});

watch(
  () => blueprintDraft.value,
  (value) => saveDraft('blueprint', value),
  { deep: true }
);
watch(
  () => rulesetDraft.value,
  (value) => saveDraft('ruleset', value),
  { deep: true }
);

async function hydrateDrafts() {
  const bp = await loadDraft('blueprint');
  const rs = await loadDraft('ruleset');
  if (bp?.value) {
    blueprintDraft.value = bp.value;
  }
  if (rs?.value) {
    rulesetDraft.value = rs.value;
  }
}

async function loadInventory() {
  const data = await api.get<{ inventory: any[] }>('/inventory');
  inventory.value = data.inventory;
}

async function loadBlueprints() {
  const data = await api.get<{ blueprints: any[] }>('/blueprints/list');
  blueprints.value = data.blueprints;
}

async function loadRulesets() {
  const data = await api.get<{ rulesets: any[] }>('/rulesets/list');
  rulesets.value = data.rulesets;
}

function loadBlueprint(blueprint: any) {
  editingBlueprintId = Number(blueprint.id);
  blueprintDraft.value = {
    name: blueprint.name,
    chassis: blueprint.chassis,
    lane_pref: blueprint.lane_pref,
    modules: blueprint.modules || [],
    stats: blueprint.stats || { hp: 100, speed: 10, power: 10 }
  };
}

function loadRuleset(ruleset: any) {
  editingRulesetId = Number(ruleset.id);
  rulesetDraft.value = {
    name: ruleset.name,
    rules: ruleset.rules || []
  };
}

async function saveBlueprint() {
  if (editingBlueprintId) {
    await api.post('/blueprints/update', { ...blueprintDraft.value, blueprint_id: editingBlueprintId });
    status.value = 'Blueprint updated.';
  } else {
    await api.post('/blueprints/create', blueprintDraft.value);
    status.value = 'Blueprint created.';
  }
  await loadBlueprints();
}

async function saveRuleset() {
  if (editingRulesetId) {
    await api.post('/rulesets/update', { ...rulesetDraft.value, ruleset_id: editingRulesetId });
    status.value = 'Ruleset updated.';
  } else {
    await api.post('/rulesets/create', rulesetDraft.value);
    status.value = 'Ruleset created.';
  }
  await loadRulesets();
}

async function validateBlueprint() {
  const data = await api.post<{ valid: boolean; errors: string[] }>('/validate/blueprint', blueprintDraft.value);
  status.value = data.valid ? 'Blueprint valid.' : `Blueprint invalid: ${data.errors.join(' ')}`;
}

async function validateRuleset() {
  const data = await api.post<{ valid: boolean; errors: string[] }>('/validate/ruleset', rulesetDraft.value);
  status.value = data.valid ? 'Ruleset valid.' : `Ruleset invalid: ${data.errors.join(' ')}`;
}
</script>
