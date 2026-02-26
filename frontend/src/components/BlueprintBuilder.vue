<template>
  <div class="panel col">
    <h3>Blueprint Builder</h3>
    <label>
      Name
      <input v-model="local.name" placeholder="Vanguard Mk1" />
    </label>
    <label>
      Chassis Slug
      <input v-model="local.chassis" placeholder="chassis-starter" />
    </label>
    <label>
      Lane Preference
      <select v-model="local.lane_pref">
        <option value="left">Left</option>
        <option value="mid">Mid</option>
        <option value="right">Right</option>
        <option value="adaptive">Adaptive</option>
      </select>
    </label>
    <div class="grid two">
      <label>
        HP
        <input type="number" min="1" max="999" v-model.number="local.stats.hp" />
      </label>
      <label>
        Speed
        <input type="number" min="1" max="999" v-model.number="local.stats.speed" />
      </label>
      <label>
        Power
        <input type="number" min="1" max="999" v-model.number="local.stats.power" />
      </label>
    </div>

    <div class="card col">
      <strong>Modules</strong>
      <div v-for="(module, idx) in local.modules" :key="idx" class="row">
        <select v-model="module.type">
          <option value="weapon">Weapon</option>
          <option value="defense">Defense</option>
          <option value="mobility">Mobility</option>
          <option value="utility">Utility</option>
        </select>
        <input v-model="module.slug" placeholder="module-laser-mk1" />
        <button class="ghost" type="button" @click="removeModule(idx)">Remove</button>
      </div>
      <button type="button" class="ghost" @click="addModule">Add Module</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue';

const props = defineProps<{ modelValue: any }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>();

const local = reactive<any>({
  name: props.modelValue?.name ?? '',
  chassis: props.modelValue?.chassis ?? 'chassis-starter',
  lane_pref: props.modelValue?.lane_pref ?? 'adaptive',
  modules: Array.isArray(props.modelValue?.modules) ? [...props.modelValue.modules] : [],
  stats: {
    hp: props.modelValue?.stats?.hp ?? 100,
    speed: props.modelValue?.stats?.speed ?? 10,
    power: props.modelValue?.stats?.power ?? 10
  }
});

watch(
  () => local,
  () => emit('update:modelValue', JSON.parse(JSON.stringify(local))),
  { deep: true }
);

function addModule() {
  local.modules.push({ type: 'weapon', slug: '' });
}

function removeModule(idx: number) {
  local.modules.splice(idx, 1);
}
</script>
