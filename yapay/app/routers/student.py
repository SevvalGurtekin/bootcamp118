from fastapi import APIRouter, Request
from fastapi.templating import Jinja2Templates
import os

router = APIRouter()
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

@router.get("/student/dashboard")
def student_dashboard(request: Request):
    return templates.TemplateResponse("student_dashboard.html", {"request": request})
