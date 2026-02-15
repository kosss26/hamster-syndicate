import { Outlet } from 'react-router-dom'
import BottomMenu from './BottomMenu'

export default function Layout() {
  return (
    <div>
      <Outlet />
      <BottomMenu />
    </div>
  )
}
