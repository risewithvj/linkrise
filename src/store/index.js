import { create } from 'zustand';
export const useStore = create((set)=>({theme:'light',toasts:[],setTheme:(theme)=>set({theme})}));
