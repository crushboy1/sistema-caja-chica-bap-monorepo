<template>
  <div class="p-6 max-w-xl mx-auto bg-white rounded-xl shadow-md space-y-4">
    <h2 class="text-2xl font-bold">Declaración de Gastos</h2>

    <form @submit.prevent="agregarGasto" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">Descripción:</label>
        <input v-model="nuevoGasto.descripcion" type="text" class="mt-1 block w-full border rounded-md p-2" required />
      </div>

      <div>
        <label class="block text-sm font-medium">Monto (S/):</label>
        <input v-model.number="nuevoGasto.monto" type="number" class="mt-1 block w-full border rounded-md p-2" required />
      </div>

      <div>
        <label class="block text-sm font-medium">Fecha:</label>
        <input v-model="nuevoGasto.fecha" type="date" class="mt-1 block w-full border rounded-md p-2" required />
      </div>

      <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
        Agregar Gasto
      </button>
    </form>

    <div v-if="gastos.length" class="pt-4">
      <h3 class="text-xl font-semibold mb-2">Lista de Gastos</h3>
      <ul class="space-y-2">
        <li v-for="(gasto, index) in gastos" :key="index" class="border-b pb-2">
          <div class="flex justify-between">
            <span>{{ gasto.descripcion }} - S/ {{ gasto.monto.toFixed(2) }}</span>
            <span class="text-sm text-gray-500">{{ gasto.fecha }}</span>
          </div>
        </li>
      </ul>

      <div class="text-right font-bold mt-4">
        Total: S/ {{ totalGastos.toFixed(2) }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DeclaracionDeGastos',
  data() {
    return {
      nuevoGasto: {
        descripcion: '',
        monto: 0,
        fecha: ''
      },
      gastos: []
    };
  },
  computed: {
    totalGastos() {
      return this.gastos.reduce((sum, gasto) => sum + gasto.monto, 0);
    }
  },
  methods: {
    agregarGasto() {
      this.gastos.push({ ...this.nuevoGasto });
      this.nuevoGasto = { descripcion: '', monto: 0, fecha: '' };
    }
  }
};
</script>

<style scoped>
/* Estilos opcionales adicionales */
</style>
