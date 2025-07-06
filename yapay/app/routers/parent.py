from fastapi import APIRouter, Request
from fastapi.templating import Jinja2Templates
import os

router = APIRouter()
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

@router.get("/parent/dashboard")
def parent_dashboard(request: Request):
    return templates.TemplateResponse("parent_dashboard.html", {"request": request})
