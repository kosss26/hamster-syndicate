import { Outlet } from 'react-router-dom'
import BottomMenu from './BottomMenu'

export default function Layout() {
  return (
    <div className="pb-24"> {/* Padding bottom for menu */}
      <Outlet />
      <BottomMenu />
    </div>
  )
}
