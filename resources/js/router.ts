import { createRouter, createWebHistory } from 'vue-router'
import ProjectDetail from '@/views/projects/ProjectDetail.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/projects/:projectId',
      name: 'project-detail',
      component: ProjectDetail,
      props: (route) => ({ projectId: Number(route.params.projectId) }),
    },
  ],
})

export default router
